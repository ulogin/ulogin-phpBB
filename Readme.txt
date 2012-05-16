=== uLogin - виджет авторизации через социальные сети ===
Donate link: http://ulogin.ru/
Tags: ulogin, login, social, authorization
Requires at least: 3.0
Tested up to: 3.0.11
Stable tag: 1.1
License: GPL3
Форма авторизации uLogin через социальные сети. Улучшенный аналог loginza.

== Description ==

uLogin — это инструмент, который позволяет пользователям получить единый доступ к различным Интернет-сервисам без необходимости повторной регистрации,
а владельцам сайтов — получить дополнительный приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)

== Installation ==


Замечания:

1. Скопируйте содержимое директории root в корневой каталог phpbb (/includes/ulogin/ulogin_register.php,/includes/ulogin/class_ulogin.php,/includes/ulogin/class_JSON.php);
2. Создать в базе данных следующую таблицу:
  CREATE TABLE prefix_ulogin (
    `id` int(10) unsigned NOT NULL auto_increment,
    `userid` int(10) NOT NULL,
    `identity` text,
    PRIMARY KEY (`id`)
  ) ENGINE=MyISAM;
  где prefix - префикс таблиц phpBB(по умолчанию phpbb);
3.Изменить следующие файлы в каталоге phpbb:

- includes/functions.php:
  найти 
    'SITE_LOGO_IMG'			=> $user->img('site_logo'),
  добавить ниже  
		'ULOGIN'	=> urlencode( append_sid(generate_board_url() . "/ucp.$phpEx", 'mode=register') ),
		'ULOGIN_SHOWN' 	=> 'vkontakte,odnoklassniki,mailru,facebook', /* Сервисы, выводимые сразу */
		'ULOGIN_HIDDEN'	=> 'other', /* Сервисы, выводимые при наведении */
		/* полный список сервисов по адрес: http://ulogin.ru/ */

- ucp.php:
  найти 
    $module->load('ucp', 'register');
  заменить на 
	if (isset($_POST['token']) && $_POST['token'])
	{
	    $module->load('ulogin', 'register');
	}
	else
	{
	    $module->load('ucp', 'register');
	}

- styles/prosilver/template/overall_header.html (для шаблона prosilver) :
  найти 
	<!-- IF not S_IS_BOT -->
	<!-- IF S_DISPLAY_MEMBERLIST --><li class="icon-members"><a href="{U_MEMBERLIST}" title="{L_MEMBERLIST_EXPLAIN}">{L_MEMBERLIST}</a></li><!-- ENDIF -->
	<!-- IF not S_USER_LOGGED_IN and S_REGISTER_ENABLED and not (S_SHOW_COPPA or S_REGISTRATION) --><li class="icon-register"><a href="{U_REGISTER}">{L_REGISTER}</a></li><!-- ENDIF -->
	<li class="icon-logout"><a href="{U_LOGIN_LOGOUT}" title="{L_LOGIN_LOGOUT}" accesskey="x">{L_LOGIN_LOGOUT}</a></li>
	<!-- ENDIF -->
  добавить ниже 
	<!-- IF not S_USER_LOGGED_IN and not S_IS_BOT -->
	<li style="margin-top: 5px;">
	  <script src="http://ulogin.ru/js/ulogin.js"></script>
	  <div id="uLogin" x-ulogin-params="display=small&fields=first_name,last_name,email,photo&optional=bdate,country,city&providers={ULOGIN_SHOWN}&hidden={ULOGIN_HIDDEN}&redirect_uri={ULOGIN}"></div>
	</li>
	<!-- ENDIF -->

- styles/prosilver/template/login_body.html (для шаблона prosilver) :
  найти 
	<!-- IF LOGIN_ERROR --><div class="error">{LOGIN_ERROR}</div><!-- ENDIF -->
  добавить ниже
	<dl>
	  <dt><label for="ulogin2">Войти через:</label></dt>
          <dd>
	    <div id="ulogin2" x-ulogin-params="display=small&fields=first_name,last_name,email,photo&optional=bdate,country,city&providers={ULOGIN_SHOWN}&hidden={ULOGIN_HIDDEN}&redirect_uri={ULOGIN}"></div>
	  </dd>
	</dl>

5. После редактирования шаблонов не забудьте сбросить кэш шаблонов: Стили -> Шаблоны. Нажать кнопку Обновить у текущего шаблона.


English installation guide: install_mod.xml 