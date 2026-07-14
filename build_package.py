#!/usr/bin/env python3
"""
Build the Joomla! Audio Archive installation package.

Place this script in the project root, directly beside the
pkg_audioarchive directory, LICENSE, and README.md.
"""

from __future__ import annotations

import argparse
import re
import shutil
import sys
import tempfile
import xml.etree.ElementTree as ElementTree
import zipfile
from pathlib import Path
from typing import Iterable, Sequence


PACKAGE_DIRECTORY_NAME = "pkg_audioarchive"
PACKAGE_MANIFEST_NAME = "pkg_audioarchive.xml"
PACKAGE_INSTALL_SCRIPT_NAME = "install.php"

IGNORED_DIRECTORY_NAMES = frozenset(
    (
        "__pycache__",
        ".git",
        ".idea",
        ".vscode",
    )
)

IGNORED_FILE_NAMES = frozenset(
    (
        ".DS_Store",
        "Thumbs.db",
    )
)

IGNORED_FILE_SUFFIXES = frozenset(
    (
        ".pyc",
        ".pyo",
    )
)

VALID_VERSION_PATTERN = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._+-]*$")


class BuildError(RuntimeError):
    """@brief Indicates an invalid source tree or a package build failure."""


def parse_arguments(arguments: Sequence[str]) -> argparse.Namespace:
    """
    @brief Parse command-line arguments.
    @param arguments Command-line arguments without the executable name.
    @return Parsed arguments.
    """

    parser = argparse.ArgumentParser(
        description=(
            "Build the versioned Joomla Audio Archive installation package."
        )
    )
    parser.add_argument(
        "--source",
        type=Path,
        default=None,
        help=(
            "Path to the pkg_audioarchive source directory. "
            "Defaults to ./pkg_audioarchive beside this script."
        ),
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=None,
        help=(
            "Explicit path of the resulting package ZIP. "
            "By default, the filename is generated from the manifest version, "
            "for example pkg_audioarchive_v0-6-3.zip."
        ),
    )
    return parser.parse_args(arguments)


def local_xml_name(tag_name: str) -> str:
    """
    @brief Remove an optional XML namespace from an element name.
    @param tag_name Full ElementTree tag name.
    @return Local XML element name.
    """

    return tag_name.rsplit("}", 1)[-1]


def parse_package_manifest(
    manifest_path: Path,
) -> tuple[str, tuple[str, ...]]:
    """
    @brief Read the package version and nested extension ZIP names.
    @param manifest_path Path to pkg_audioarchive.xml.
    @return Package version and ordered nested extension archive names.
    @throws BuildError If the manifest is invalid or incomplete.
    """

    try:
        root = ElementTree.parse(manifest_path).getroot()
    except ElementTree.ParseError as error:
        raise BuildError(
            "Cannot parse package manifest '%s': %s" % (manifest_path, error)
        ) from error
    except OSError as error:
        raise BuildError(
            "Cannot read package manifest '%s': %s" % (manifest_path, error)
        ) from error

    version = ""

    for element in root.iter():
        if local_xml_name(element.tag) == "version":
            version = (element.text or "").strip()
            break

    if not version:
        raise BuildError(
            "The package manifest does not contain a non-empty <version> element."
        )

    if VALID_VERSION_PATTERN.fullmatch(version) is None:
        raise BuildError(
            "The package manifest contains an invalid version value: '%s'."
            % version
        )

    archive_names: list[str] = []

    for element in root.iter():
        if local_xml_name(element.tag) != "file":
            continue

        value = (element.text or "").strip()

        if not value.lower().endswith(".zip"):
            continue

        archive_path = Path(value)

        if archive_path.name != value:
            raise BuildError(
                "Nested extension archive paths are not supported: '%s'." % value
            )

        if value in archive_names:
            raise BuildError(
                "The package manifest lists '%s' more than once." % value
            )

        archive_names.append(value)

    if not archive_names:
        raise BuildError(
            "The package manifest does not list any nested extension ZIP files."
        )

    return version, tuple(archive_names)


def make_default_output_name(version: str) -> str:
    """
    @brief Create the versioned outer package filename.
    @param version Version read from the package manifest.
    @return Filename such as pkg_audioarchive_v0-6-3.zip.
    """

    filename_version = version.replace(".", "-")
    return "%s_v%s.zip" % (PACKAGE_DIRECTORY_NAME, filename_version)


def should_ignore(relative_path: Path) -> bool:
    """
    @brief Determine whether a source file should be omitted from an archive.
    @param relative_path Path relative to the extension source directory.
    @return True when the file should be omitted.
    """

    if any(part in IGNORED_DIRECTORY_NAMES for part in relative_path.parts):
        return True

    if relative_path.name in IGNORED_FILE_NAMES:
        return True

    if relative_path.suffix.lower() in IGNORED_FILE_SUFFIXES:
        return True

    return False


def collect_source_files(source_directory: Path) -> tuple[Path, ...]:
    """
    @brief Collect all files belonging to one Joomla extension.
    @param source_directory Extension source directory.
    @return Sorted tuple of source files.
    """

    files = (
        path
        for path in source_directory.rglob("*")
        if path.is_file()
        and not should_ignore(path.relative_to(source_directory))
    )

    return tuple(
        sorted(
            files,
            key=lambda path: path.relative_to(source_directory).as_posix(),
        )
    )


def validate_archive(archive_path: Path) -> None:
    """
    @brief Test the CRC data of every entry in a ZIP archive.
    @param archive_path ZIP archive to test.
    @throws BuildError If the ZIP cannot be read or contains a damaged entry.
    """

    try:
        with zipfile.ZipFile(archive_path, "r") as archive:
            damaged_entry = archive.testzip()
    except (OSError, zipfile.BadZipFile) as error:
        raise BuildError(
            "Cannot validate ZIP archive '%s': %s" % (archive_path, error)
        ) from error

    if damaged_entry is not None:
        raise BuildError(
            "ZIP archive '%s' contains a damaged entry: %s"
            % (archive_path, damaged_entry)
        )


def build_extension_archive(
    source_directory: Path,
    archive_path: Path,
) -> int:
    """
    @brief Build one installable Joomla extension ZIP.
    @param source_directory Extension source directory.
    @param archive_path Destination ZIP path.
    @return Number of files added to the archive.
    @throws BuildError If the source directory is missing or empty.
    """

    if not source_directory.is_dir():
        raise BuildError(
            "Extension source directory does not exist: '%s'."
            % source_directory
        )

    source_files = collect_source_files(source_directory)

    if not source_files:
        raise BuildError(
            "Extension source directory contains no packageable files: '%s'."
            % source_directory
        )

    try:
        with zipfile.ZipFile(
            archive_path,
            mode="w",
            compression=zipfile.ZIP_DEFLATED,
            compresslevel=9,
            strict_timestamps=False,
        ) as archive:
            for source_path in source_files:
                archive_name = source_path.relative_to(
                    source_directory
                ).as_posix()
                archive.write(source_path, archive_name)
    except OSError as error:
        raise BuildError(
            "Cannot build extension archive '%s': %s" % (archive_path, error)
        ) from error

    validate_archive(archive_path)
    return len(source_files)


def build_outer_package(
    manifest_path: Path,
    install_script_path: Path,
    extension_archives: Iterable[Path],
    output_path: Path,
) -> None:
    """
    @brief Assemble the final Joomla package ZIP.
    @param manifest_path Package XML manifest.
    @param install_script_path Package installation script.
    @param extension_archives Nested extension ZIP archives.
    @param output_path Destination package ZIP.
    """

    try:
        with zipfile.ZipFile(
            output_path,
            mode="w",
            compression=zipfile.ZIP_DEFLATED,
            compresslevel=9,
            strict_timestamps=False,
        ) as archive:
            archive.write(
                manifest_path,
                PACKAGE_MANIFEST_NAME,
                compress_type=zipfile.ZIP_DEFLATED,
                compresslevel=9,
            )
            archive.write(
                install_script_path,
                PACKAGE_INSTALL_SCRIPT_NAME,
                compress_type=zipfile.ZIP_DEFLATED,
                compresslevel=9,
            )

            for extension_archive in extension_archives:
                archive.write(
                    extension_archive,
                    extension_archive.name,
                    compress_type=zipfile.ZIP_STORED,
                )
    except OSError as error:
        raise BuildError(
            "Cannot build package archive '%s': %s" % (output_path, error)
        ) from error

    validate_archive(output_path)


def resolve_paths(
    arguments: argparse.Namespace,
) -> tuple[Path, Path | None]:
    """
    @brief Resolve source and optional explicit output paths.
    @param arguments Parsed command-line arguments.
    @return Source directory and optional explicit output ZIP path.
    """

    project_root = Path(__file__).resolve().parent

    source_directory = (
        arguments.source.expanduser().resolve()
        if arguments.source is not None
        else project_root / PACKAGE_DIRECTORY_NAME
    )

    output_path = (
        arguments.output.expanduser().resolve()
        if arguments.output is not None
        else None
    )

    return source_directory, output_path


def build_package(
    package_directory: Path,
    explicit_output_path: Path | None,
) -> Path:
    """
    @brief Build all nested extension archives and the final package archive.
    @param package_directory pkg_audioarchive source directory.
    @param explicit_output_path Optional caller-supplied package ZIP path.
    @return Path of the generated package ZIP.
    @throws BuildError If validation or archive creation fails.
    """

    manifest_path = package_directory / PACKAGE_MANIFEST_NAME
    install_script_path = package_directory / PACKAGE_INSTALL_SCRIPT_NAME

    if not package_directory.is_dir():
        raise BuildError(
            "Package source directory does not exist: '%s'."
            % package_directory
        )

    if not manifest_path.is_file():
        raise BuildError(
            "Package manifest does not exist: '%s'." % manifest_path
        )

    if not install_script_path.is_file():
        raise BuildError(
            "Package install script does not exist: '%s'."
            % install_script_path
        )

    version, extension_archive_names = parse_package_manifest(manifest_path)

    output_path = (
        explicit_output_path
        if explicit_output_path is not None
        else Path(__file__).resolve().parent / make_default_output_name(version)
    )

    output_path.parent.mkdir(parents=True, exist_ok=True)

    print("Package version: %s" % version)
    print("Building nested extension archives:")

    with tempfile.TemporaryDirectory(
        prefix="audioarchive-build-"
    ) as temporary_directory_name:
        temporary_directory = Path(temporary_directory_name)
        extension_archives: list[Path] = []

        for archive_name in extension_archive_names:
            source_name = Path(archive_name).stem
            source_directory = package_directory / source_name
            archive_path = temporary_directory / archive_name

            file_count = build_extension_archive(
                source_directory,
                archive_path,
            )
            extension_archives.append(archive_path)

            print(
                "  %-36s %5d files"
                % (archive_name, file_count)
            )

        temporary_package_path = temporary_directory / output_path.name

        build_outer_package(
            manifest_path,
            install_script_path,
            extension_archives,
            temporary_package_path,
        )

        if output_path.exists():
            output_path.unlink()

        shutil.move(
            str(temporary_package_path),
            str(output_path),
        )

    print()
    print("Package created successfully:")
    print("  %s" % output_path)
    print("  %.2f MiB" % (output_path.stat().st_size / (1024.0 * 1024.0)))

    return output_path


def main() -> int:
    """
    @brief Program entry point.
    @return Zero on success, otherwise one.
    """

    arguments = parse_arguments(sys.argv[1:])

    try:
        package_directory, explicit_output_path = resolve_paths(arguments)
        build_package(package_directory, explicit_output_path)
    except BuildError as error:
        print("Build failed: %s" % error, file=sys.stderr)
        return 1
    except OSError as error:
        print("Build failed: %s" % error, file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
