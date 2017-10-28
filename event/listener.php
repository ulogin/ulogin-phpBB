<?php
/**
*
* @package ulogin
*
*/

namespace uloginteam\ulogin\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $controller_helper;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var string phpEx */
	protected $php_ext;

	/** @var string */
	protected $root_path;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config        $config             Config object
	 * @param \phpbb\controller\helper    $controller_helper  Controller helper object
	 * @param \phpbb\template\template    $template           Template object
	 * @param \phpbb\user                 $user               User object
	 * @param \phpbb\request\request      $request            Request object
	 * @param string                      $root_path          phpBB root path
	 * @param string                      $php_ext            phpEx
	 * @return \phpbb\boardrules\event\listener
	 * @access public
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\request\request $request,
		$root_path,
		$php_ext)
	{
		$this->config = $config;
		$this->template = $template;
		$this->user = $user;
		$this->php_ext = $php_ext;
		$this->request = $request;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'	=>	'load_language_on_setup',
			'core.page_header'	=>	'add_page_header_data',
		);
	}

	/**
	* Load common files during user setup
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'uloginteam/ulogin',
			'lang_set' => 'ulogin',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}


	public function add_page_header_data($event)
	{
		$display = 'small';
		$display2 = 'panel';
		$fields = 'first_name,last_name,email';
		$optional = 'bdate,country,photo,city';
		$providers = 'vkontakte,odnoklassniki,facebook,mailru';
		$hidden = 'other';
		$redirect_uri = urlencode(generate_board_url() . '/ulogin/login?redirect=' . urlencode(generate_board_url(true) . '/' . $this->user->page['page']));
		$callback = 'uloginCallback';


		if (!$this->config['ulogin_id1']){
			$data_ulogin1 = "display=$display&fields=$fields&optional=$optional&providers=$providers&hidden=$hidden&redirect_uri=$redirect_uri&callback=$callback";
			$data_uloginid1 = '';
		} else {
			$data_ulogin1 = "redirect_uri=$redirect_uri&callback=$callback";
			$data_uloginid1 = $this->config['ulogin_id1'];
		}

		if (!$this->config['ulogin_id2']){
			$data_ulogin2 = "display=$display2&fields=$fields&optional=$optional&providers=$providers&hidden=$hidden&redirect_uri=$redirect_uri&callback=$callback";
			$data_uloginid2 = '';
		} else {
			$data_ulogin2 = "redirect_uri=$redirect_uri&callback=$callback";
			$data_uloginid2 = $this->config['ulogin_id2'];
		}

		$this->template->assign_vars(array(
			'DATA_ULOGIN1'       => $data_ulogin1,
			'DATA_ULOGIN2'       => $data_ulogin2,
			'DATA_ULOGINID1'     => $data_uloginid1,
			'DATA_ULOGINID2'     => $data_uloginid2,
		));



		$this->template->assign_vars(array(
			'ULOGIN_MESSAGE' => $this->request->variable('msg', '', false, \phpbb\request\request_interface::REQUEST),
		));
	}
}
