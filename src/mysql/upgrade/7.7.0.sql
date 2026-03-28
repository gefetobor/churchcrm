CREATE TABLE IF NOT EXISTS event_images_eim (
  eim_event_id INT NOT NULL,
  eim_image_path VARCHAR(255) NULL,
  eim_image_alt VARCHAR(255) NULL,
  eim_updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (eim_event_id),
  CONSTRAINT fk_event_images_event
    FOREIGN KEY (eim_event_id) REFERENCES events_event (event_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
