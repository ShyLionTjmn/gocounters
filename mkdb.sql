DROP TABLE IF EXISTS ds;
DROP TABLE IF EXISTS rs;
DROP TABLE IF EXISTS crs;
DROP TABLE IF EXISTS cs;
DROP TABLE IF EXISTS ss;
DROP TABLE IF EXISTS users;

CREATE TABLE users ( # Пользователи
  user_id INTEGER NOT NULL AUTO_INCREMENT,
  user_login VARCHAR(128) NOT NULL,
  user_md5_password VARCHAR(256) NOT NULL,
  user_password_count INTEGER NOT NULL, # счетчик изменений пароля, для контроля сеанса
  user_rights VARCHAR(1024) NOT NULL,
  user_name VARCHAR(256) NOT NULL,
  user_last_login BIGINT NOT NULL, # точное время крайней авторизации
  user_last_activity BIGINT NOT NULL, # точное время крайней активности
  user_blocked INTEGER NOT NULL, # 0 - в работе, time() - время когда был заблокирован
  user_block_reason VARCHAR(256) NOT NULL, # причина блокировки
  user_deleted BIGINT NOT NULL, # 0 - в работе, time() - время когда был удален. На самом деле не удаляется, а скрывается из интерфейса для сохранения истории и т.п.
  ts BIGINT NOT NULL, #
  change_by INTEGER NOT NULL, # логин пользователя, внесшего последние изменения
  PRIMARY KEY pk_user_id(user_id),
  FOREIGN KEY fk_change_by(change_by) REFERENCES users(user_id),
  UNIQUE KEY uk_user_login(user_deleted,user_login)
);

CREATE TABLE ss ( # Таблица поставщиков услуг
  s_id  INTEGER NOT NULL AUTO_INCREMENT,
  s_short_name VARCHAR(128) NOT NULL, # Кратое название при отображении в списках
  s_full_name VARCHAR(1024) NOT NULL, # Полное название
  s_contacts VARCHAR(1024) NOT NULL, # Контактная информация
  s_deleted BIGINT NOT NULL, # 0 - в работе, time() - время когда был удален. На самом деле не удаляется, а скрывается из интерфейса для сохранения истории и т.п.
  ts BIGINT NOT NULL, #
  change_by INTEGER NOT NULL, # логин пользователя, внесшего последние изменения
  FOREIGN KEY fk_change_by(change_by) REFERENCES users(user_id),
  PRIMARY KEY pk_s_id(s_id),
  UNIQUE KEY uk_s_short_name(s_short_name)
);

CREATE TABLE cs ( # Таблица счетчиков ЖКХ
  c_id	INTEGER NOT NULL AUTO_INCREMENT,
  c_type VARCHAR(64) NOT NULL, # цифробуквенное обозначение типа счетчика, однозначно указывающее на протокол взаимодействия и набор считываемых параметров
                               # для разных типов запускаются разные демоны
  c_connect VARCHAR(64) NOT NULL, # строка подключения для демона, например:
                                   # 1.1.1.1:50/48758345
  c_location VARCHAR(256) NOT NULL, # Словесное описание места установки
  c_coords VARCHAR(256) NOT NULL, # Географические координаты для системы отображения на карте, формат задается фронтендом
  c_descr VARCHAR(256) NOT NULL, # Словесное описание
  c_fk_s_id INTEGER NOT NULL, # Поставщик услуг
  c_number VARCHAR(256) NOT NULL, # Номер в системе учета поставщика услуг
  c_comment VARCHAR(1024) NOT NULL, # Дополнительный коментарий, где техподдержка может писать пояснения, текущие проблемы и т.п.
  c_model VARCHAR(256) NOT NULL, # Модель, заполняется демоном опроса, автоматически
  c_serial VARCHAR(256) NOT NULL, # Серийный номер, опрос переходит в состояние ошибки если номер не совпадает. Значение auto атоматически будет заменено на считаный номер.
  c_paused INT NOT NULL, # 0 - в работе, 1 - на паузе
  c_deleted BIGINT NOT NULL, # 0 - в работе, time() - время когда был удален. На самом деле не удаляется, а скрывается из интерфейса для сохранения истории и т.п.
  c_last_ok BIGINT NOT NULL, # time() - время когда было крайнее удачное считывание
  c_last_error BIGINT NOT NULL, #  time() - время когда было крайнее неудачное считывание
  c_error VARCHAR(256) NOT NULL, # текст крайней ошибки
  c_tz VARCHAR(256) NOT NULL, # Временная зона, согласно которой будут посуточно сохраняться показания
  ts BIGINT NOT NULL, #
  change_by INTEGER NOT NULL, # логин пользователя, внесшего последние изменения
  FOREIGN KEY fk_change_by(change_by) REFERENCES users(user_id),
  UNIQUE KEY uk_c_connect(c_connect, c_deleted),
  PRIMARY KEY pk_c_id(c_id),
  FOREIGN KEY fk_c_fk_s_id(c_fk_s_id) REFERENCES ss(s_id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE crs ( # таблица коррекции показаний, прибавляется при отображении
  cr_id INTEGER NOT NULL AUTO_INCREMENT,
  cr_name VARCHAR(32) NOT NULL, # имя переменной
  cr_value DECIMAL(20,3) NOT NULL, # значение коррекции
  cr_fk_c_id INTEGER NOT NULL,
  ts BIGINT NOT NULL, #
  change_by INTEGER NOT NULL, # логин пользователя, внесшего последние изменения
  FOREIGN KEY fk_change_by(change_by) REFERENCES users(user_id),
  PRIMARY KEY pk_cr_id(cr_id),
  FOREIGN KEY fk_cr_fk_c_id(cr_fk_c_id) REFERENCES cs(c_id),
  UNIQUE KEY uk_cr_fk_c_id_cr_name(cr_fk_c_id,cr_name)
);

CREATE TABLE rs ( # Таблица суточных показаний, ключ YYYYMMDD во временной зоне устройства!
  r_id INTEGER NOT NULL AUTO_INCREMENT,
  r_name VARCHAR(32) NOT NULL, # имя переменной
  r_value VARCHAR(32) NOT NULL, # крайнее значение при считывании
  r_date VARCHAR(16) NOT NULL, # YYYYMMDD дата считывания для посуточной истории
  r_time BIGINT NOT NULL, # точное время крайнего считывания
  r_fk_c_id INTEGER NOT NULL,
  PRIMARY KEY pk_r_id(r_id),
  FOREIGN KEY fk_r_fk_c_id(r_fk_c_id) REFERENCES cs(c_id),
  UNIQUE KEY uk_r_date_fk_c_id_r_name(r_date,r_fk_c_id,r_name)
);

CREATE TABLE ds ( # Таблица крайних значений информационных показаний устройства
  d_id INTEGER NOT NULL AUTO_INCREMENT,
  d_name VARCHAR(32) NOT NULL, # имя переменной
  d_value VARCHAR(256) NOT NULL, # крайнее значение при считывании
  d_time BIGINT NOT NULL, # точное время крайнего считывания
  d_fk_c_id INTEGER NOT NULL,
  PRIMARY KEY pk_d_id(d_id),
  FOREIGN KEY fk_d_fk_c_id(d_fk_c_id) REFERENCES cs(c_id),
  UNIQUE KEY uk_d_fk_c_id_d_name(d_fk_c_id,d_name)
);

