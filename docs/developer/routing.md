# Routing

The site service `Punga\Component\Audioarchive\Site\Service\Router` implements component routing, supported by `RouteHelper`, menu rules, and `ArchiveMenuItemResolver`.

## Clip routes

Clip Detail routes use a clean alias-based public form in the current implementation. Legacy and stale alias routes can redirect to the canonical route.

Canonical metadata is emitted for the resolved clip. Replacing an original file does not change the route.

## Menu context

When several Archive menu items exist, route generation attempts to retain the correct accessible menu context, language, category/tag restrictions, and configured view behaviour.

Tag links resolve to an appropriate target Archive menu item. Previous/Next queries use the active Archive rules rather than a global unrestricted clip order.

## Direct component routes

The site dispatcher enforces the component-wide frontend access setting before direct `/component/audioarchive` views and controllers are dispatched.

## Browser origin state

Exact Archive or Sound Board origin URLs are kept out of the canonical clip URL. See [Frontend state](frontend-state.md).
