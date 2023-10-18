<?php
// Heading
$_['heading_title']        = 'Host Management';

// Text
$_['text_extension']       = 'Extensions';
$_['text_success']         = 'You have changed Host Management!';
$_['text_success_hosts']   = 'Hosts have been updated!';
$_['text_close']           = 'Close';
$_['text_edit']            = 'Manage Hosts';
$_['text_host']            = 'Host';
$_['text_admin']           = 'Admin';
$_['text_catalog']         = 'Catalog';
$_['text_dir_title']       = 'Directories';
$_['text_dir_notice']      = 'Before enabling, check if admin and public directories are correct.';
$_['text_dir_admin']       = 'Admin directory';
$_['text_dir_public']      = 'Public directory';
$_['text_dir_root']        = '<i>host document root</i>';
$_['text_status_title']    = 'Status';
$_['text_status_notice']   = 'Enabling the extension changes config files, disabling restores them
                              using <strong>default</strong> host.';
$_['text_status_info']     = 'Disable the extension <strong>before</strong> operations that change
                              or move <i>config.php</i> files (like moving admin directory), and
                              before uninstallng this extension (recommended).';
$_['text_hosts_title']     = 'Hosts';

// Entry
$_['entry_status']         = 'Enabled';
$_['entry_host_protocol']  = 'Protocol';
$_['entry_hostname' ]      = 'Hostname (FQDN)';
$_['entry_default' ]       = 'Default';
$_['entry_http' ]          = 'http';
$_['entry_https' ]         = 'https';
$_['entry_hostname_desc' ] = 'fully qualified domain name';

// Button
$_['button_host_add' ]     = 'New host';
$_['button_host_remove' ]  = 'Remove host';


// Error
$_['error_permission']     = 'Warning: You do not have permission to modify security settings!';
$_['error_warning']        = 'Warning: Please check the form carefully for errors!';
$_['error_file_access']    = 'Warning: Could not get access for: %s file.!';
$_['error_read_access']    = 'Warning: Could not get read access for: %s file.!';
$_['error_write_access']   = 'Warning: Could not get write access for: %s file.!';
$_['error_read']           = '<strong>Error: </strong> Reading admin config failed. Could not match
                              protocol, hostname and directory.';
$_['error_protocol']       = 'Invalid protocol. Only http and https are allowed.';
$_['error_hostname']       = 'Invalid hostname. Lowercase letters, numbers, hyphens and dots are
                              allowed. It must start and end with leter or number.';
$_['error_dir']            = 'Invalid %s directory. Letters, numbers, hyphens, underscores and
                              forward slashes are allowed.';
$_['error_same']           = 'Server and catalog must have same protocol and hostname.';
$_['error_default_count']  = 'Warning: You must have one default host!';
$_['error_notice']         = 'Check your admin <i>config.php</i> file syntax.';
$_['error_update_urls']    = 'Could not match URLs within config file!';
$_['error_http_server']    = 'Could not match "HTTP_SERVER" definition!';
$_['error_http_catalog']   = 'Could not match "HTTP_CATALOG" definition!';
$_['error_php_tag']        = 'Could not match "php" opening tag!';
$_['error_restore']        = 'Could not find edited section of config file!';
$_['error_default_host']   = 'Could not find default host!';
$_['error_status']         = 'Status was not changed!';

// Messages
$_['msg_error_prefix']     = 'Error: ';
$_['msg_success_prefix']   = 'Success: ';