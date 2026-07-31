# Access and security

## Joomla access layers

A public clip request can be restricted by:

- Component-wide frontend access level
- Menu-item access
- Clip publication state and dates
- Clip access level
- Category and ancestor-category state/access
- Download-specific access level
- Editing ACL

Every media endpoint repeats the relevant checks. A visible link is never treated as authorisation.

## Administrator requests

State-changing administrator actions require a valid Joomla session, CSRF token, validated input, and suitable ACL permission.

## Uploads and imports

Validation combines configured extension and MIME rules with PHP Fileinfo and structural media inspection. Managed names are generated internally. Import sources must remain inside the configured inbox.

## SQL and output

Use Joomla query APIs and bound values. Escape frontend output according to context. Descriptions and Custom Fields use Joomla filtering and rendering.

## External processes

Only administrator-controlled executable paths are used. Arguments are supplied separately rather than interpolated into shell command strings. Execution has timeouts, captured output/error, and exit-code validation.

## Public interaction endpoints

Play counts, ratings, playlist resolution, Sound Board resolution, and other interactions validate clip eligibility and request tokens where applicable. Clients cannot submit arbitrary counter deltas.

## Error handling

Public errors must not expose filesystem paths, executable paths, SQL details, stack traces, or secrets.
