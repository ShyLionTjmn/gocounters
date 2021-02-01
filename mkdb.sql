CREATE TABLE counters (
  c_id	INTEGER NOT NULL AUTO_INCREMENT,
  c_type VARCHAR(64) NOT NULL,
  c_connect VARCHAR(256) NOT NULL,
  c_location VARCHAR(256) NOT NULL,
  c_coords VARCHAR(256) NOT NULL,
  c_descr VARCHAR(256) NOT NULL,
  c_comment VARCHAR(1024) NOT NULL,
  c_paused INT NOT NULL,
  c_deleted INT NOT NULL,
  ts TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  change_by VARCHAR(256) NOT NULL,
  UNIQUE KEY uk_c_connect(c_connect, deleted),
  PRIMARY KEY pk_c_id(c_id)
);
