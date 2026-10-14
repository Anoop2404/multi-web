# Automatic event admin logins

New Sahodaya fest events receive a dedicated portal-user account when the event is created through Eloquent. This includes region/partition/phase child events; school-conducted rounds are excluded. Provisioning is idempotent and does not replace existing manually assigned administrators.

The username is `event<ID>` (a numeric suffix is added only if the username is taken). Each account receives a random ten-character lowercase/digit temporary password, excluding ambiguous characters. It must change its password on first login. Passwords are hashed on the user; the shareable temporary password is encrypted on `fest_event_admin_accounts`. A password fingerprint prevents sharing a stale password after an unrelated admin reset. The generated `.invalid` email is an internal placeholder for schemas requiring an email; login uses the username, and password recovery uses the admin reset action.

Generated accounts have the `event_admin` role, their event assignment, and operational permissions excluding `fest.finance` and `finance.view`. The persistent user finance-restriction flag also denies finance if permissions are subsequently granted or the account-link record is removed. Existing manual accounts keep their current permissions. Main-event accounts use existing descendant scope; regional accounts cannot access sibling regions or the main event.

Finance paths/reports and financial write fields are blocked on the server. Mixed Inertia/API payloads omit financial fields; the registration-register CSV omits its fee columns for these users. Operational settings/item forms omit financial inputs, keeping existing fee settings intact. Shared registration-fee gates continue to apply during approval.

## Share and reset

Only the Sahodaya administrator and superadmin receive temporary credentials. Open **Portal users** or **Event staff** and choose **Copy WhatsApp login message**. The message is plain text with a WhatsApp heading:

```text
*Event name — Region name*
Username: event71
Password: <temporary password>
Login: https://your-domain/login
```

Event staff also has separate username/password copy buttons. A manual-copy text box appears when clipboard access is unavailable. Once a user changes the temporary password, the original is no longer offered. **Reset temporary password** invalidates the current password and produces a new message.

On **Portal users**, each user row has **Copy username**, **Copy password**, and **Copy login · WhatsApp** actions. This includes older manually provisioned event/region users when their current temporary password is available. After the user changes their password, only username copying remains available until **Reset PW** creates a fresh shareable password. A generic Reset PW also works for automatically provisioned event users. Password-copy data is never provided to event-admin users themselves or other non-owner staff.

Existing events are not bulk-provisioned by the migration. Their administrators can use **Portal users → + Event admin login**, select an event or regional event without an automatic login, and create it. The new credentials appear immediately with the WhatsApp copy button. **Event staff → Create missing event logins** is also available. Both actions create accounts for the selected event and its descendants without changing existing generated logins.

## Deployment

Deploy code and built assets, then run the new tenant migration `2026_11_21_000001_create_fest_event_admin_accounts_table.php` through the project's tenant migration process. For existing dedicated Sahodaya databases, the documented deployment command is:

```bash
php artisan sahodaya:provision-databases --no-create
```

Run the normal central/shared-database migration workflow as appropriate for the installation. No new automatic accounts are created until the migration is installed. Do not change APP_KEY when deploying: it protects the encrypted temporary credentials.
