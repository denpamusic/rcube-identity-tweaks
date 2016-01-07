<?php

	$config['identity_tweaks_style_list'] = true;
	$config['identity_tweaks_max_identities'] = 10;
	$config['identity_tweaks_dissallowed_characters'] = '-';
	$config['identity_tweaks_dissallowed_names'] = array(
		'root',
		'*mailer-daemon*',
		'*postmaster*',
		'*hostmaster*',
		'*webmaster*',
		'*dnsmaster*',
		'admin',
		'*administrator*',
		'*noreply*',
		'help',
		'*helpdesk*',
		'*billing*',
		'*unsubscribe*',
		'ftp',
		'info',
		'www*',
		'support',
		'abuse',
		'*security*',
		'reports',
		'dmarc_agg',
		'test',
		'mail',
		'email',
		'sales',
		'staff',
		'robot',
		'owner',
		'jobs',
		'*customerservice*',
		'domains',
		'*swrn*',
		'*swrn.net*',
		'spam',
		'orders',
		'nobody',
		'contact',
		'service',
		'moderator',
		'operator',
		'system',
		'server',
		'call',
		'domain',
	);
	$config['identity_tweaks_dissallowed_users'] = array();
	$config['identity_tweaks_trusted_users'] = array('shadowwolf@swrn.net');

?>