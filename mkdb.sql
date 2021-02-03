DROP TABLE IF EXISTS ds;
DROP TABLE IF EXISTS rs;
DROP TABLE IF EXISTS cs;

CREATE TABLE cs ( # Таблица счетчиков ЖКХ
  c_id	INTEGER NOT NULL AUTO_INCREMENT,
  c_type VARCHAR(64) NOT NULL, # цифробуквенное обозначение типа счетчика, однозначно указывающее на протокол взаимодействия и набор считываемых параметров
                               # для разных типов запускаются разные демоны
  c_connect VARCHAR(64) NOT NULL, # строка подключения для демона, например:
                                   # 1.1.1.1:50/48758345
  c_location VARCHAR(256) NOT NULL, # Словесное описание места установки
  c_coords VARCHAR(256) NOT NULL, # Географические координаты для системы отображения на карте, формат задается фронтендом
  c_descr VARCHAR(256) NOT NULL, # Словесное описание
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
  change_by VARCHAR(256) NOT NULL, # логин пользователя, внесшего последние изменения
  UNIQUE KEY uk_c_connect(c_connect, c_deleted),
  PRIMARY KEY pk_c_id(c_id)
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
  

INSERT INTO cs SET
   c_type='gost-c-electro-1p'
  ,c_connect='10.10.13.131:50'
  ,c_serial='auto'
  ,c_location='Республики 243/1, тестовый стенд'
  ,c_descr='Тестовый стенд'
  ,c_tz='Asia/Yekaterinburg'
;
