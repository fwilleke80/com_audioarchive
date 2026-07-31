# Ratings

Punga Audio Archive provides Like and Dislike ratings.

## Configuration

The dedicated **Ratings** component-options tab controls:

- Whether ratings are enabled
- Whether everyone, registered users, or nobody may vote

Archive and Clip Detail display can be enabled independently and overridden by Archive menu items.

## Storage and requests

Rating requests use Joomla session and CSRF protection. Guest voting does not require a Joomla account when it is permitted by configuration.

Ratings are separate from aggregate play and download counters. Punga Analytics rating events are not currently documented as part of the standard event bridge.
