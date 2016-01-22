<?php
/**
 * Основные параметры WordPress.
 *
 * Скрипт для создания wp-config.php использует этот файл в процессе
 * установки. Необязательно использовать веб-интерфейс, можно
 * скопировать файл в "wp-config.php" и заполнить значения вручную.
 *
 * Этот файл содержит следующие параметры:
 *
 * * Настройки MySQL
 * * Секретные ключи
 * * Префикс таблиц базы данных
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** Параметры MySQL: Эту информацию можно получить у вашего хостинг-провайдера ** //
/** Имя базы данных для WordPress */
define('DB_NAME', 'history_school');

/** Имя пользователя MySQL */
define('DB_USER', 'vozniy');

/** Пароль к базе данных MySQL */
define('DB_PASSWORD', '7hRaZl');

/** Имя сервера MySQL */
define('DB_HOST', 'localhost');

/** Кодировка базы данных для создания таблиц. */
define('DB_CHARSET', 'utf8mb4');

/** Схема сопоставления. Не меняйте, если не уверены. */
define('DB_COLLATE', '');

/**#@+
 * Уникальные ключи и соли для аутентификации.
 *
 * Смените значение каждой константы на уникальную фразу.
 * Можно сгенерировать их с помощью {@link https://api.wordpress.org/secret-key/1.1/salt/ сервиса ключей на WordPress.org}
 * Можно изменить их, чтобы сделать существующие файлы cookies недействительными. Пользователям потребуется авторизоваться снова.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'Lm#,>q+q>+KRHF,&o{l7Wj-,Ola&owM_JK9@EUh~YkQM<U+}j_r|DMv@6UKUO|l=');
define('SECURE_AUTH_KEY',  '9~|,B/6On<z2T~)[P?L0WN<ZM8X& *5kjdrxK0@+j2|[:T^WkfsgDVO|AXnr(m|%');
define('LOGGED_IN_KEY',    'p6fxVJ*d_!>R-n$&W+g8Dx9-9`Q]?WN*#=I-Ssi[<n5<P-kEZI%,Ig>LPV6JWC)v');
define('NONCE_KEY',        'T0> ir+/tzQuB17ZLllJA79f7!.9hhgl0$/eWZYkd9ate@$dSmV]YLBBJ7}nD8Ma');
define('AUTH_SALT',        'K}t0$F$8)-%U(,g#EeMVOhug=I ~:HTKE4`Bq}rsLh(],?pEc?|uQ>F&7$J~7Z{+');
define('SECURE_AUTH_SALT', '&nA*P1lwKrlX2X;_@4*W-h&-;A~ rR?o{5S-:+l2XK>04`E+/a0vA2G+Bv+9lPRQ');
define('LOGGED_IN_SALT',   '<+)(SfnPEA8.fB&$:BQX!GDapP^(MA](S5]ZCQnGP/Co54><=,8{><-7$%4$_b9;');
define('NONCE_SALT',       'v}An=pgS%&a:i^_vuw{=nJh7tUmME/~3vcpMy|UeU#McV1s}M(|QW?qU-SpV;eB#');

/**#@-*/

/**
 * Префикс таблиц в базе данных WordPress.
 *
 * Можно установить несколько сайтов в одну базу данных, если использовать
 * разные префиксы. Пожалуйста, указывайте только цифры, буквы и знак подчеркивания.
 */
$table_prefix  = 'hist_';

/**
 * Для разработчиков: Режим отладки WordPress.
 *
 * Измените это значение на true, чтобы включить отображение уведомлений при разработке.
 * Разработчикам плагинов и тем настоятельно рекомендуется использовать WP_DEBUG
 * в своём рабочем окружении.
 * 
 * Информацию о других отладочных константах можно найти в Кодексе.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* Это всё, дальше не редактируем. Успехов! */

/** Абсолютный путь к директории WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Инициализирует переменные WordPress и подключает файлы. */
require_once(ABSPATH . 'wp-settings.php');
