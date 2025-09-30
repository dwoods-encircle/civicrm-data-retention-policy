# CiviCRM Data Retention Policy

This extension provides a configurable data retention policy for CiviCRM installations. Administrators can define how long specific record types should be kept, and a scheduled job enforces those rules by deleting records whose activity is older than the configured window.

## Features

* New **Data Retention Policy** settings screen under `Administer » System Settings`.
* Individual retention periods (in years) for contacts, participants, and contributions.
* Separate control (in days) for how long deleted contacts remain in the trash before being purged permanently.
* Scheduled job (`Apply Data Retention Policies`) which deletes records older than the defined retention window using the CiviCRM API.
* Logging for records which cannot be deleted by the scheduled job.

## Installation

1. Copy the extension directory to your CiviCRM extension directory.
2. Enable the extension from **Administer » System Settings » Extensions**.

## Configuration

1. Navigate to **Administer » System Settings » Data Retention Policy**.
2. Enter the retention period in years for each entity you want to purge automatically. Use `0` (or leave blank) to disable deletion for that entity. Configure the number of days that contacts should remain in the trash before they are permanently removed.
3. Save the settings.

The scheduled job evaluates the following activity dates when determining whether a record should be deleted:

| Entity        | Activity field(s) used |
| ------------- | ---------------------- |
| Contacts      | `last_activity_date`, falling back to `modified_date` or `created_date` |
| Contacts (trash) | `modified_date` |
| Participants  | `modified_date`, falling back to `register_date` or `create_date` |
| Contributions | `receive_date`, falling back to `modified_date` or `create_date` |

## Scheduled Job

The extension registers a scheduled job named **Apply Data Retention Policies**. Review the job in **Administer » System Settings » Scheduled Jobs** and adjust the execution schedule to match your compliance needs. When run, the job reports how many records were deleted per entity and logs any failures to the CiviCRM log.

> ⚠️ **Important:** Deletion is permanent. Ensure that the configured retention windows align with your organisation's policies and any legal requirements before enabling the scheduled job.

## Development

The extension key is `uk.co.encircle.dataretentionpolicy`. Contributions are welcome via pull requests.