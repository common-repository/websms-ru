<?php
/*
Plugin Name: WEBSMS.RU WordPress
Description: SMS уведомления о событиях WordPress через шлюз WEBSMS.RU
Version: 1.00
Author: WEBSMS.RU
Author URI: http://websms.ru
Plugin URI: http://websms.ru/services/sending/request#
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if ( !is_callable( 'is_plugin_active' ) ) 
{
	require_once str_replace(array(site_url(),'\\','//'), ARRAY(ABSPATH,'/','/'), admin_url()).'includes/plugin.php'; 
}

if (!class_exists('Http_Websms')) 
{
  require_once(plugin_dir_path( __FILE__ ).'httpwebsms.php');
}  

add_action('plugins_loaded', 'websms_ru_wordpress', 0);

function websms_ru_wordpress() 
{
	return new websms_ru_wordpress();
}

if ( ! function_exists( 'websms_errorlog' ) ) {

  function websms_errorlog( $log ) 
  {
    if ( is_array($log) || is_object($log) ) {
       error_log(print_r($log, true));
    } else {
       error_log($log);
    }
  }

}

class websms_ru_wordpress { 
  private $net;
  private $sKeys;
  private $eKeys;
  private $eFields;
  private $dsts;
  
	public function __construct() 
	{
    $this->sKeys = array( 'websms-login', 'websms-password', 'websms-phone1', 'websms-phone2', 'websms-phone3', 'websms-phone4' );
    $this->dsts  = array( '1', '2', '3', '4' );
    $this->eKeys = array( 'websms-user_register', 'websms-wp_login', 'websms-transition_post_status', 'websms-post_updated' );
    $this->eFields = array( 'user_register'=> array( 'event'   => 'Зарегистрировался новый пользователь', 
                                                     'default' => 'Зарегистрировался пользователь {USER_LOGIN} на сайт {SITE_URL}'
                                                    ),
                            'wp_login' => array( 'event'  => 'Пользователь залогинился', 
                                                'default' => 'Залогинился пользователь {USER_LOGIN} на сайт {SITE_URL}' 
                                                ),
                            'transition_post_status'=> array( 'event'   => 'Опубликован новый пост',
                                                              'default' => 'Опубликован новый пост {POST_TITLE} на сайте {SITE_URL} пользователем {USER_LOGIN}' 
                                                             ),
                            'post_updated' => array( 'event'  => 'Пост изменен', 
                                                    'default' => 'Пост ID {POST_ID} был изменен' 
                                                    ),
                           );                            
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('user_register', array($this, 'process_user_register'), 20, 1);
		add_action('wp_login', array($this, 'process_user_login'), 20, 2);
		add_action('transition_post_status', array($this, 'process_new_post'), 20, 3);
		add_action('post_updated', array($this, 'process_update_post'), 20, 3);		
		register_deactivation_hook(__FILE__, array($this, 'deactivation'));
		$this->net = new Http_Websms(get_option('websms-login'), get_option('websms-password'));
	}

  /**
  *   отправка СМС-сообщения через шлюз websms.ru
  **/
  function send_sms( $telNum, $messText )
  {
    if ( !$telNum || !$messText ) { 
      return false; 
    }

    try {
       $ret = $this->net->sendSms($telNum, $messText);
       if ( isset($ret['error_code']) ) {
         if ( $ret['error_code'] == 0 ) {
            return $ret['message_id'];
         } else {
            return $ret['error_mess'];
         }
       }
       return false;
    } catch (Exception $ex) {
       return $ex->getMessage();
    }
  }
	 
	public function deactivation() 
	{
    foreach( $this->sKeys as $k ) { delete_option($k); }
    foreach( $this->eKeys as $k ) { delete_option($k); }
	}
	
	public function admin_menu() 
	{
    add_options_page('websms_ru_wordpress', 'WEBSMS.RU', 'manage_options', 'websms_ru_settings', array(&$this, 'options'));
	}
	
  /**
  *   настройка плагина
  **/
	public function options() 
	{
      /*  сохраняем параметры, если была команда Сохранить  */ 
      //  сохраняем строковые параметры
      foreach( $this->sKeys as $k ) { 
        if ( isset($_POST[$k]) ) {
          $text_option = substr(esc_attr(sanitize_text_field($_POST[$k])), 0, 50); 
          update_option($k,$text_option); 
        }  
      } 
      // сохраняем массив связванных с событиями оповещений
      foreach( $this->eKeys as $k ) { 
        if ( isset($_POST[$k.'-dest']) && isset($_POST[$k.'-message']) ) {
          $destOpt = $this->sanitize_dest_key($_POST[$k.'-dest']);
          $messTextOpt = substr(trim(esc_attr(sanitize_textarea_field($_POST[$k.'-message']))), 0, 1570);
          $eventOpt = array('dest'=>$destOpt, 'message'=>$messTextOpt); 
          update_option($k, $eventOpt);
        }  
      } 
      /* отправляем тестовое сообщение, если нужно, и возвращаемся на страницу с результатом  */
			if ( isset($_POST['websms-login']) && isset($_POST['websms-password']) ) {
        $this->net->setLogin(get_option('websms-login', ''));
        $this->net->setPassword(get_option('websms-password', ''));
        if ( isset($_POST['test']) && get_option('websms-phone1') ) {
          $rez = $this->send_sms(get_option('websms-phone1', ''), 'Test message from websms.ru plugin for WordPress');
          if ( is_numeric($rez) ) {
            wp_redirect(admin_url('admin.php?page=websms_ru_settings&test_success=1'));
          } else {
            wp_redirect(admin_url('admin.php?page=websms_ru_settings&test_error='.$rez));
          } 
        } else {
          wp_redirect(admin_url('admin.php?page=websms_ru_settings&saved=1'));
        }
      }      
      ?>
		<div class="wrap woocommerce">
			<form method="post" id="mainform" action="<?php echo admin_url('admin.php?page=websms_ru_settings') ?>">
				<h2>SMS оповещения о событиях WordPress через шлюз WEBSMS.RU - Настройка плагина</h2>
				<table><tr><td style="vertical-align:middle"><a href="http://cab.websms.ru" target="_blank"><img src="<?php echo plugin_dir_url(__FILE__).'images/logo_websms.png'?>"/></a></td><td style="width:20px;"></td><td style="vertical-align:middle"><a href="http://cab.websms.ru" target="_blank">Личный кабинет на WEBSMS.RU</a></td></tr></table>
				<table class="form-table">
					<tr><th>Логин пользователя websms</th><td colspan="2"><input title="Имя входа в личный кабинет WEBSMS.RU" required type="text" name="websms-login" id="websms-login" value="<?php echo esc_attr(get_option('websms-login','')) ?>" size="50" maxlength="50"/></td><tr/>
					<tr><th>Пароль http</td><td colspan="2"><input title="Пароль для работы с http api сервиса WEBSMS.RU" required type="text" name="websms-password" id="websms-password" value="<?php echo esc_attr(get_option('websms-password','')) ?>" size="50" maxlength="50"/></td><tr/>
					<tr><th>Телефон 1</td><td colspan="2"><input required type="text" name="websms-phone1" id="websms-phone1" value="<?php echo $this->net->checkPhone(get_option('websms-phone1',''))  ?>" size="50" maxlength="20" title="На этот номер могут, если нужно приходить оповещения"/>&nbsp;<small>Например, 79012345678 или {USER_PHONE}</small></td></tr>
					<tr><th>Телефон 2</td><td colspan="2"><input type="text" name="websms-phone2" id="websms-phone2" value="<?php echo $this->net->checkPhone(get_option('websms-phone2',''))  ?>" size="50" maxlength="20" title="На этот номер могут, если нужно приходить оповещения"/>&nbsp;<small>Например, 79012345678 или {USER_PHONE}</small></td></tr>
					<tr><th>Телефон 3</td><td colspan="2"><input type="text" name="websms-phone3" id="websms-phone3" value="<?php echo $this->net->checkPhone(get_option('websms-phone3',''))  ?>" size="50" maxlength="20" title="На этот номер могут, если нужно приходить оповещения"/>&nbsp;<small>Например, 79012345678 или {USER_PHONE}</small></td></tr>
					<tr><th>Телефон 4</td><td colspan="2"><input type="text" name="websms-phone4" id="websms-phone4" value="<?php echo $this->net->checkPhone(get_option('websms-phone4',''))  ?>" size="50" maxlength="20" title="На этот номер могут, если нужно приходить оповещения"/>&nbsp;<small>Например, 79012345678 или {USER_PHONE}</small></td></tr>
					<tr><th colspan="3">Выберите события и телефоны, на которые будут отправляться оповещения:</td></tr>
          <?php foreach( $this->eFields as $evnt => $descr ) {  
            $curOptionArr = get_option('websms-'.$evnt, array('dest'=>'0','message'=>$descr['default'])); 
            ?>
            <tr>
            <th style="width:110px"><?php echo $descr['event'] ?></th>
            <td style="width:140px">
            <input type="radio" value="0"   name="<?php echo 'websms-'.$evnt.'-dest' ?>" <?php echo ($curOptionArr['dest']=='0' ? ' checked="checked"' : '') ?> />Не&nbsp;отправлять<br/>
            <input type="radio" value="1"   name="<?php echo 'websms-'.$evnt.'-dest' ?>" <?php echo ($curOptionArr['dest']=='1' ? ' checked="checked"' : '') ?> />Телефон 1<br/>
            <input type="radio" value="2"   name="<?php echo 'websms-'.$evnt.'-dest' ?>" <?php echo ($curOptionArr['dest']=='2' ? ' checked="checked"' : '') ?> />Телефон 2<br/>
            <input type="radio" value="3"   name="<?php echo 'websms-'.$evnt.'-dest' ?>" <?php echo ($curOptionArr['dest']=='3' ? ' checked="checked"' : '') ?> />Телефон 3<br/>
            <input type="radio" value="4"   name="<?php echo 'websms-'.$evnt.'-dest' ?>" <?php echo ($curOptionArr['dest']=='4' ? ' checked="checked"' : '') ?> />Телефон 4<br/>
            <input type="radio" value="all" name="<?php echo 'websms-'.$evnt.'-dest' ?>" <?php echo ($curOptionArr['dest']=='all' ? ' checked="checked"' : '') ?> />На все
            </td>
            <td><textarea name="<?php echo 'websms-'.$evnt.'-message' ?>" rows="3" cols="60" maxlength="1570" title="Этот текст будет отправлен в СМС-сообщении в ответ на событие <?php echo $descr['event'] ?>"><?php echo esc_textarea($curOptionArr['message']) ?></textarea></td>
            </tr>
          <?php } ?>
					<tr><td colspan="3">
					  <div>Переменные, которые можно включить в текст сообщения:</div>
            <div style="background:lightcyan; padding:2px 2px 4px 4px;">
              {USER_ID} - идентификатор пользователя, {USER_LOGIN} - логин пользователя, {USER_EMAIL} - адрес эл.почты, {USER_URL} - страница пользователя, 
              {POST_ID} - идентификатор сообщения, {POST_TITLE} - заголовок сообщения, {SITE_URL} - адрес сайта, 
              {FIRST_NAME} - имя пользователя, {LAST_NAME} - фамилия пользователя
            </div>
					  <div>Переменная, которую можно указать вместо номера телефона в поле Телефон N:</div>
            <div style="background:lightcyan; padding:2px 2px 4px 4px;">
              {USER_PHONE} - можно указать в качестве телефона, на который будет отправлено оповещение, если в профиле (метаданных) пользователя есть поле с номером телефона, например, как 'billing_phone', 'user_phone' или 'mobile'
            </div>
          </td>
          </tr>
				</table>
				<br/>
				<div style="color: #ffa20f">
				<input type="submit" class="button-primary" style="color:#FFFFFF; background-color:#2f78b8;" name="save" value="Сохранить"/> 
				<?php
				if ( (isset($_GET['saved'])) && ($_GET['saved'] == '1') ) {
          echo '&nbsp;Данные сохранены';
				}
				?> 
				</div>
				<small>Сохранить произведенные изменения параметров</small><br/>				
				<div style="color:#2f78b8; margin-top:4px;">
				<input type="submit" class="button-secondary" style="color: #ffa20f" name="test" value="Тестовое сообщение"/>&nbsp; 
				<?php
				if ( (isset($_GET['test_success'])) && ($_GET['test_success'] == '1') ) {
          echo '&nbsp;Тестовое сообщение было успешно отправлено';
				}
				if ( isset($_GET['test_error']) ) {
          echo '&nbsp;<b style="color: red">Ошибка отправки тестового сообщения: '.esc_html(wp_kses_post($_GET['test_error']))."</b>";
				}
				?> 
				</div>
				<small>Параметры будут сохранены, после чего будет отправлено тестовое сообщение на Телефон 1, если он введен</small><br/>
			</form>
		</div>
<?php
	}

  /**
  *   обработка наступившего события Регистрация нового пользователя
  **/
	public function process_user_register( $userid )
	{
    $user_data = get_userdata($userid);
    $this->process_all_events('websms-user_register', $user_data);
	}

  /**
  *   обработка наступившего события Пользователь залогинился
  **/
	public function process_user_login( $user_login, $user_data )
	{
    $this->process_all_events('websms-wp_login', $user_data);
	}

  /**
  *   обработка наступившего события Опубликован новый пост
  **/
	public function process_new_post( $new_status=null, $old_status=null, $post=null )
	{
    if ( ($new_status == 'publish') && ($old_status != 'publish') ) {
      $user_data = get_user_by('id', $post->post_author);
      $this->process_all_events('websms-transition_post_status', $user_data, $post);
    }  
	}

  /**
  *   обработка наступившего события Пост изменен
  **/
	public function process_update_post( $post_ID, $post_after, $post_before )
	{
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return;
		} else {
      if ( ($post_after->post_status == 'publish') && ($post_before->post_status == 'draft') ) { 
        return;
      } else {  
        $post = get_post($post_ID);
        $user_data = get_user_by('id', $post->post_author);
        $this->process_all_events('websms-post_updated', $user_data, $post); 
      }
    }  
	}

  /**
  *   обработка событий
  **/
	public function process_all_events($event, $user_data, $post=null)
	{
    $regEventOpt = get_option($event);
		if ( isset($regEventOpt) ) {
      if ( $regEventOpt['dest'] !== '0' ) {
        if ( (get_option('websms-login')) && (get_option('websms-password')) ) {
          $searcArr = array('{USER_ID}', '{USER_LOGIN}', '{USER_EMAIL}', '{USER_URL}', '{SITE_URL}', '{FIRST_NAME}', '{LAST_NAME}');
          $replArr  = array(html_entity_decode($user_data->get('ID')), 
                            html_entity_decode($user_data->get('user_login')), 
                            html_entity_decode($user_data->get('user_email')), 
                            html_entity_decode($user_data->get('user_url')), 
                            html_entity_decode(get_site_url()), 
                            html_entity_decode($user_data->get('user_firstname')),                               
                            html_entity_decode($user_data->get('user_lastname')),
                           );         
          $messText = str_replace($searcArr, $replArr, $regEventOpt['message']);
          
          if ( isset($post) ) {
            $searcArr = array('{POST_ID}', '{POST_TITLE}');
            $replArr  = array($post->ID, $post->post_title);          
            $messText = str_replace($searcArr,$replArr,$messText);
          }
                                                                    
          foreach( $this->dsts as $d ) { 
            if ( ($d == $regEventOpt['dest']) || ($regEventOpt['dest'] == 'all') ) {
                if ( get_option('websms-phone'.$d) ) {
                  $toPhone = get_option('websms-phone'.$d,'');
                  if ( trim($toPhone) == '{USER_PHONE}' ) {
                      $toPhone = $user_data->get('billing_phone');
                      if ( $toPhone == '' ) {
                        $toPhone = $user_data->get('user_phone');
                        if ( $toPhone == '' ) {
                          $toPhone = $user_data->get('phone');
                          if ( $toPhone == '' ) {
                            $toPhone = $user_data->get('mobile');
                          }
                        }
                      }
                  }
                  if ( $toPhone !== '' ) {
                    //  websms_errorlog('$this->send_sms( Phone='.$toPhone.';  Text='.$messText.' );');
                    $this->send_sms($toPhone, $messText);                
                  }
                }
            }
          }
        }  
      }  
    }
	}
	
	function sanitize_dest_key($key)
  {
    if ( ($key=='0') || ($key=='1') || ($key=='2') || ($key=='3') || ($key=='4') || ($key=='all') ) {
      return $key;
    } else {
      return '0';
    }
  }	
}
?>