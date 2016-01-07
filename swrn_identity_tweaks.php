<?php
class swrn_identity_tweaks extends rcube_plugin
{
	public $task = 'mail|settings';

	private $user_email = null;

	private $rcmail = null;

	public function init()
	{
		$this->rcmail = rcmail::get_instance();

		if ($this->rcmail->task == 'settings') {
			$this->load_config();
			$this->add_texts('localization', true);

			if($this->rcmail->action == 'identities' && $this->rcmail->config->get('identity_tweaks_style_list')) {
				$this->include_stylesheet($this->local_skin_path() . '/swrn_identity_tweaks.css');
				$this->add_hook('identities_list',  array($this, 'identities_list'));
			}

			$this->add_hook('identity_create',  array($this, 'identity_change'));
			$this->add_hook('identity_update',  array($this, 'identity_change'));
			$this->add_hook('identity_delete',  array($this, 'identity_delete'));
			$this->add_hook('identity_form',  array($this, 'identity_form'));
		} else {
			$this->add_hook('message_before_send', array($this, 'message_headers'));
		}
	}

	public function message_headers($args)
	{
		if($this->is_alias($args['from'])) {
			$headers = $args['message']->headers();

			$user = $this->get_user_email();
			$headers['X-Original-Sender'] = $user['email'];

			$args['message']->_headers = array();
			$args['message']->headers($headers);
		}

		return $args;
	}

	public function identities_list($args)
	{
		$email_count = count($args['list']);

		$list = array();
		foreach($args['list'] as $email) {
			$list[($this->is_alias($email) ? 'aliases' : 'emails')][] = $this->is_alias($email) ? array_merge(['class' => 'alias'], $email) : $email;
		}

		usort($list['emails'], array($this, 'identity_sort'));
		usort($list['aliases'], array($this, 'identity_sort'));

		if(isset($list['aliases'][0])) {
			$list['aliases'][0]['class'] .= ' first-alias';
		}

		$args['list'] = array_merge($list['emails'], $list['aliases']);

		return $args;
	}

	private function identity_sort($a, $b)
	{
		return ($a['identity_id'] > $b['identity_id']) ? 1 : -1;
	}

	public function identity_change($args)
	{
		$identities = $this->rcmail->user->list_emails();
		$identities_count = count($identities);

		$user = $this->get_user_email();
		$user_mailhost = $this->rcmail->user->data['mail_host'];
		$identity = $this->get_email_parts( $args['record']['email'] );

		if($this->is_alias($identity)) {
			$rcube_user = $this->rcmail->user->query($args['record']['email'], $user_mailhost);
			if(!($rcube_user === false) && ($rcube_user->get_username('mail') != $this->rcmail->get_user_email())) {
				return $this->send_error(sprintf($this->gettext('useroridentityexists'), $args['record']['email']));
			}
		}

		if($disallowed_characters = $this->rcmail->config->get('identity_tweaks_dissallowed_characters')) {
			$length = strlen($disallowed_characters);
			$found_characters = '';
			for($i = 0; $i < $length; $i++) {
				if(!strpos($identity['username'], $disallowed_characters[$i]) === false) {
					$found_characters .= '"' . $disallowed_characters[$i] . '", ';
				}
			}
			if(!empty($found_characters)) {
				$found_characters = rtrim($found_characters, ', ');
				return $this->send_error(sprintf($this->gettext('disallowedcharacters'), $found_characters));
			}
		}

		if(in_array($user['email'], $this->rcmail->config->get('identity_tweaks_trusted_users', array()))) {
			return $args;
		}

		if($this->is_alias($identity) && in_array($user['email'], $this->rcmail->config->get('identity_tweaks_dissallowed_users', array()))) {
			return $this->send_error($this->gettext('usernotallowed'));
		}

		if( $identity['domain'] != $user['domain'] ) {
			return $this->send_error(sprintf($this->gettext('domainnotallowed'), $identity['domain']));
		}

		if($banned_names = $this->rcmail->config->get('identity_tweaks_dissallowed_names', array())) {
			foreach($banned_names as $name) {
				$pattern = str_replace('*', '.*', $name);
				if(preg_match("/^$pattern$/", $identity['username']) === 1) {
					return $this->send_error($this->gettext('identitynamenotavailable'));
				}
			}
		}

		if( ($identities_max = $this->rcmail->config->get('identity_tweaks_max_identities', 10)) && $identities_count >= $identities_max ) {
			return $this->send_error($this->gettext('maxidentitynumberreached'));
		}

		return $args;
	}

	public function identity_delete($args)
	{
		//$identity = $this->rcmail->user->get_identity($args['id']);

		return $args;
	}

	public function identity_form($args)
	{
		$user = $this->get_user_email();

		$origin = array(
				'type' => 'text',
				'size' => 40,
				'label' => rcube::Q($this->rcmail->gettext('email')),
				'disabled' => 'disabled',
		);

		$alias = array(
				'name' => rcube::Q($this->gettext('Alias')),
				'content' => array(
					'email' => $args['form']['addressing']['content']['email']
			)
		);

		unset($args['form']['addressing']['content']['email']);
		//unset($args['form']['addressing']['content']['standard']);

		$args['form'] = $this->array_insert_after('addressing', $args['form'], 'aliasing', $alias);
		$args['form']['addressing']['content'] = $this->array_insert_after('name', $args['form']['addressing']['content'], 'origin', $origin);

		$args['record']['origin'] = rcube_utils::idn_to_utf8($user['email']);
		$args['record']['email'] = !empty($args['record']['email']) ? $args['record']['email'] : rcube_utils::idn_to_utf8($user['email']);

		return $args;
	}

	private function is_alias($identity)
	{
		if(is_string($identity) && !(strpos($identity,'@') === false)) {
			$identity = $this->get_email_parts($identity);
		}
		$user_email = $this->get_user_email();
		return ($identity['email'] == '' || $identity['email'] == $user_email['email']) ? false : true;
	}

	private function get_user_email()
	{
		if(is_null($this->user_email)) {
			$this->user_email = $this->get_email_parts( $this->rcmail->get_user_email() );
		}

		return $this->user_email;
	}

	private function get_email_parts($email)
	{
		$email_parts = explode('@', $email);
		return (empty($email_parts) || count($email_parts) != 2) ? array('email' => $email) : array('email' => $email, 'username' => $email_parts[0], 'domain' => $email_parts[1]);
	}

	private function array_insert_before($key, array $array, $new_key, $new_value)
	{
		if(array_key_exists($key, $array)) {
			$result = array();
			foreach ($array as $k => $value) {
				if ($k === $key) {
					$result[$new_key] = $new_value;
				}
				$result[$k] = $value;
			}
			return $result;
		}

		return false;
	}

	private function array_insert_after($key, array $array, $new_key, $new_value)
	{
		if(array_key_exists($key, $array)) {
			$result = array();
			foreach ($array as $k => $value) {
				$result[$k] = $value;
				if ($k === $key) {
					$result[$new_key] = $new_value;
				}
			}
			return $result;
		}

		return false;
	}

	private function send_error($message)
	{
		$args = array();
		$args['abort'] = true;
		$args['result'] = false;
		$args['message'] = $message;

		return $args;
	}
}
?>