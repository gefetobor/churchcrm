# Event Image Support (Mockup + Implementation Design)

## Goal
Allow admins to attach an image to an event when creating/updating in `EventEditor.php`, and include that image in event reminder emails using the current reminder template flow.

## Current Baseline
- Event create/update UI: `src/EventEditor.php`
- Event API create/update: `src/api/routes/calendar/events.php`
- Reminder send service: `src/ChurchCRM/Service/EventReminderService.php`
- Reminder email class/template: `src/ChurchCRM/Emails/notifications/EventReminderEmail.php` -> `UnifiedNotificationEmail.html.twig`
- Configurable reminder templates: `sEventReminderTemplateHtml`, `sEventReminderTemplateText` in `src/ChurchCRM/dto/SystemConfig.php`

## UX Mockup (Event Editor)
Add a new row under `Event Description` and before `Address`.

```text
+---------------------------------------------------------------+
| Event Image (Optional)                                        |
| [ Choose File ]  JPG/PNG/WEBP, max 2MB                        |
|                                                               |
| Current image preview (if exists):                            |
|  +---------------------------+                                |
|  |        [ image ]          |  [Remove Image]               |
|  +---------------------------+                                |
|                                                               |
| Alt Text: [_______________________________] (optional)         |
| Used in reminder email for accessibility                       |
+---------------------------------------------------------------+
```

### Behavior
- Create event:
  - Admin can upload one image.
  - If upload omitted, event has no image.
- Edit event:
  - Show preview if image exists.
  - Allow replace image with new file.
  - Allow remove image (`Remove Image` checkbox/button).

## Data Model Design
Store image metadata in a dedicated table keyed by event:
- `event_images_eim.eim_event_id` (PK/FK to `events_event.event_id`)
- `event_images_eim.eim_image_path` (varchar 255, nullable): relative image path
- `event_images_eim.eim_image_alt` (varchar 255, nullable): optional alt text

Migration:
- `src/mysql/upgrade/7.7.0.sql`
  - `CREATE TABLE IF NOT EXISTS event_images_eim (...)`

## File Storage Design
Store uploaded image in filesystem, consistent with current image strategy:
- Directory: `/Images/EventReminders/Events/<eventId>/`
- Filename: `cover.<ext>` where ext in `jpg|png|webp`
- DB stores relative web path:
  - `/Images/EventReminders/Events/<eventId>/cover.jpg`

Validation:
- MIME allowed: `image/jpeg`, `image/png`, `image/webp`
- Max size: 2MB (or configurable)
- Strip path traversal risk by never trusting original filename

## UI + Form Changes (`EventEditor.php`)
1. Change form to support file upload:
   - `enctype="multipart/form-data"`
2. Add controls:
   - file input `EventImageFile`
   - hidden/current `EventImageCurrent`
   - optional `EventImageAlt`
   - optional `EventImageRemove` checkbox
3. On save:
   - process file upload server-side in existing create/update branch
   - save/remove file and set event image fields

## API Changes (`api/routes/calendar/events.php`)
For API parity, support optional image metadata fields in JSON flow:
- `Image` (string URL/path) for API-only clients
- `ImageAlt` (string)

Note:
- Actual binary upload can remain in `EventEditor.php` first.
- Optional future endpoint for direct upload:
  - `POST /api/events/{id}/image` (multipart)

## Reminder Service Integration (`EventReminderService.php`)
### New tokens
Add tokens when rendering reminder templates:
- `eventImageUrl`: absolute URL to event image (or empty)
- `eventImageAlt`: alt text (or fallback: event title)

### Template usage
Keep current event reminder template, just extend token set so admins can insert:

```twig
{% if eventImageUrl %}
  <p style="margin:0 0 12px 0;">
    <img src="{{ eventImageUrl }}" alt="{{ eventImageAlt }}" style="max-width:100%;height:auto;border-radius:8px;">
  </p>
{% endif %}
```

### Important service adjustment
Current service strips all `<img>` tags (`stripInlineImagesFromBody`).  
To support event images, change this behavior to one of:
1. Remove that strip step entirely, or
2. Keep stripping user-authored inline images but allow image from `eventImageUrl` token.

Recommended:
- Keep sanitization (`InputUtils::sanitizeEmailHTML`) and remove blanket `stripInlineImagesFromBody` stripping.

## Backward Compatibility
- Existing events remain valid with null image fields.
- Existing templates continue to work without changes.
- Image display is conditional.

## Security Notes
- Validate mime type with `finfo`.
- Enforce max upload size.
- Store images under controlled path, fixed filename.
- Sanitize alt text with `InputUtils::sanitizeText`.
- Use `SystemURLs::getURL()` + stored path for safe absolute URL generation.

## Phased Delivery Plan
1. **Phase 1**: EventEditor upload + DB columns + model fields.
2. **Phase 2**: Reminder tokens + template update guidance.
3. **Phase 3**: API upload endpoint (optional), cleanup job for orphaned files.

## Acceptance Criteria
- Admin can upload/replace/remove event image in Event Editor.
- Event saves image path + alt text.
- Reminder email can render event image via template token.
- Existing reminders without images unchanged.
- No unsafe image types accepted.
