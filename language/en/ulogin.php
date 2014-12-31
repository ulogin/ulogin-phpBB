<?php

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}


$lang = array_merge($lang, array(
	'ULOGIN_ID1'                => 'Идентификатор виджета uLogin №1',
	'ULOGIN_ID1_EXPLAIN'        => 'Идентификатор виджета в шапке форума. Пустое поле - виджет по умолчанию. Идентификатор виджета можно получить в личном кабинете на сайте <strong><a href = "http://ulogin.ru/">ulogin.ru</a></strong>.',
	'ULOGIN_ID2'                => 'Идентификатор виджета uLogin №2',
	'ULOGIN_ID2_EXPLAIN'        => 'Идентификатор виджета на странице входа. Пустое поле - виджет из поля №1',
	'ULOGIN_LABEL_UCP'          => 'Войти через:'

));
