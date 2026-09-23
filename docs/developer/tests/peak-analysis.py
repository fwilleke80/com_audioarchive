"""@brief Verify the production FFmpeg filter measures source channels before downmix/downsampling."""
from pathlib import Path
import math
import re
import subprocess
import tempfile
import struct

root: Path = Path(__file__).resolve().parents[3]
source: str = (root / 'pkg_audioarchive/com_audioarchive/administrator/src/Service/Analysis/WaveformGeneratorService.php').read_text()
match: re.Match[str] | None = re.search(r"'(aformat=sample_fmts=dbl,astats=[^']+)'", source)
assert match is not None
filter_chain: str = match.group(1)
cases: list[tuple[str, float | None]] = [('0.25',20*math.log10(.25)),('0.25|0.75',20*math.log10(.75)),('0.5|-0.5',20*math.log10(.5)),('1',0.0),('1.25',20*math.log10(1.25)),('0',None)]
with tempfile.TemporaryDirectory() as folder:
    for index, (signal, expected) in enumerate(cases):
        pcm: Path = Path(folder) / f'{index}.pcm'
        result: subprocess.CompletedProcess[str] = subprocess.run(['ffmpeg','-hide_banner','-nostdin','-nostats','-v','info','-f','lavfi','-i',f'aevalsrc={signal}:s=44100:d=0.05','-af',filter_chain,'-ac','1','-ar','8000','-acodec','pcm_s16le','-f','s16le','-y',str(pcm)],capture_output=True,text=True,check=True)
        peaks: list[str] = re.findall(r'Peak level dB:\s*([^\s]+)',result.stderr)
        assert peaks, result.stderr
        measured: float = float(peaks[-1])
        assert (not math.isfinite(measured)) if expected is None else abs(measured-expected)<0.00001
        if signal == '0.5|-0.5':
            samples: bytes = pcm.read_bytes()
            assert max(abs(value[0]) for value in struct.iter_unpack('<h',samples)) == 0, 'downmix must cancel while source peak remains -6.02 dBFS'
    invalid: subprocess.CompletedProcess[str] = subprocess.run(['ffmpeg','-v','error','-i',str(Path(folder)/'missing.wav'),'-f','null','-'],capture_output=True,text=True)
    assert invalid.returncode != 0
print('7 FFmpeg peak-analysis cases passed (including anti-phase, floating overs and silence).')
