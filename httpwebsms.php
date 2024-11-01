<?php
/**
** Класс, включающий методы для работы с ресурсом websms.ru по http 
**
 $net = new Http_Websms("логин","пароль");
 try {
     $ret = $net->getSaldo();
     print_r($ret);
 } catch (Exception $ex) {
     print $ex->getMessage();
 }
**/
class Http_Websms {
  private $login = "";
  private $password = ""; 
  private $url = "http://cab.websms.ru"; 
  private $optHeader = "Content-type: application/x-www-form-urlencoded; charset=UTF-8"; 

  public function __construct($login, $password) {
    if ($login !== null) {
      $this->login = $login;
    }
    if ($password !== null) {
      $this->password = $password;
    }
  }

  public function setLogin($login) {
    if ($login !== null) {
      $this->login = $login;
    }
  }

  public function setPassword($password) {
    if ($password !== null) {
      $this->password = $password;
    }
  }

  /**   корректировка номера телефона получателя   **/
  public function checkPhone($phone) {
      $phone = preg_replace('/[^0-9]/', '', $phone);
      
      if(substr($phone, 0, 1) == 8 && strlen($phone) == 11) {
          return '7'.substr($phone, 1, 10);
      }     
      return $phone;
  }

  /**   метод осуществляет отправку СМС-сообщения $mess на адрес $telNum   **/
  public function sendSms($telNum, $mess){
    $message_id = 0;
    $packet_id  = 0;
    $phone = "";
    $parts = 0;
    $cost  = "";
    $saldo_after = "";
    try {
      $telNum = $this->checkPhone($telNum);
      $datastr = 'http_username='.urlencode($this->login).'&http_password='.urlencode($this->password).'&phone_list='.$telNum.'&message='.urlencode($mess);
      $xml = $this->xmlRequest("http_in6.asp", $datastr);
      $errCode = $xml->httpIn['error_num'];
      $errCode = (int)$errCode;
      $errMess = "Неизвестная ошибка";
      switch ($errCode) {
          case 0: 
              $errMess = "Сообщение успешно принято";
              $message_id = (int)$xml->httpIn->sms['message_id'];
              $packet_id  = (int)$xml->httpIn['packet_id'];
              $phone = (string)$xml->httpIn->sms['message_phone'];
              $parts = (int)$xml->httpIn->sms['message_parts'];
              $cost  = (string)$xml->httpIn->sms['message_cost'];
              $saldo_after = (string)$xml->httpIn['balance_after'];
              break;  
          case 1:
              $errMess = 'Неверный логин, пароль';
              break;
          case 2:
              $errMess = 'Доступ заблокирован';
              break;
          case 3:
              $errMess = 'На счете недостаточно средств';
              break;
          case 4:
              $errMess = 'IP адрес заблокирован';
              break;
          case 5:
              $errMess = 'Персональные настройки запрещают отправку по HTTP';
              break;
          case 6:
              $errMess = 'IP-адрес не указан в персональных настройках';
              break;
          case 9:
              $errMess = 'Доступ модератору закрыт (при наличии назначенного дополнительного доступа)';
              break;
          case 10:
              $errMess = 'Недопустимые символы в адресатах';
              break;
          case 11:
              $errMess = 'Не задан текст сообщения - message';
              break;
          case 12:
              $errMess = 'Не заданы адресаты - phone_list';
              break;
          case 13:
              $errMess = 'Сервис временно недоступен';
              break;
          case 17:
              $errMess = 'Процедура отправки занята';
              break;
          case 19:
              $errMess = 'Данное сообщение дублирует предыдущее';
              break;
          case 21:
              $errMess = 'Не указан пароль - http_password';
              break;
          case 22:
              $errMess = 'Не указан логин - http_username';
              break;
          case 23:
              $errMess = 'Недозволительное имя отправителя';
              break;
          default:
              $errMess = sprintf('Нераспознанная ошибка %s', $errCode);
      }
    } catch (Exception $ex) {
      $errCode = -100;
      $errMess = $ex->getMessage();
    }
    $arr1 = array('error_code' => $errCode,
                  'error_mess' => $errMess, 
                  'message_id' => $message_id,
                  'packet_id'  => $packet_id,
                  'phone'      => $phone,
                  'parts'      => $parts,
                  'cost'       => $cost,
                  'saldo_after' => $saldo_after
                  );
    return $arr1;
  }

  /**   метод получает состояние ранее отправленного сообщения по уникальному $message_id   **/
  public function getStatus($message_id){
    $status   = 0;
    $statInfo = "";
    $phone    = "";
    $message  = "";
    $zone     = 0;
    $parts    = 0;
    $cost     = "";
    $deliverDate = "";
    try {
      $datastr = 'http_username='.urlencode($this->login).'&http_password='.urlencode($this->password).'&message_id='.$message_id;
      $xml = $this->xmlRequest("http_out5.asp", $datastr); 

      $errCode = $xml->httpIn['error_num'];
      $errCode = (int)$errCode;
      if ($errCode==0) {
        $status   = (int)$xml->httpOut->sms['result_id'];
        $statInfo = (string)$xml->httpOut->sms['result_info'];
        $phone    = (string)$xml->httpOut->sms['message_phone'];
        $message  = (string)$xml->httpOut->sms['message'];
        $zone     = (int)$xml->httpOut->sms['message_zone'];
        $parts    = (int)$xml->httpOut->sms['message_parts'];
        $cost     = (string)$xml->httpOut->sms['message_cost'];
        $deliverDate = (string)$xml->httpOut->sms['delivered_date'];
       }  
    } catch (Exception $ex) {
      $errCode = -100;
      $message = $ex->getMessage();
    }
    $arr1 = array('error_code' => $errCode,
                  'status'     => $status, 
                  'statInfo'   => $statInfo,
                  'phone'      => $phone,
                  'message'    => $message,
                  'zone'       => $zone,
                  'parts'      => $parts,
                  'cost'       => $cost,
                  'deliverDate'=> $deliverDate 
                  ); 
    return $arr1;
  }

  /**   метод получает текущий остаток на счете клиента   **/
  public function getSaldo(){
    $saldo = "0,00";
    try {
      $datastr = 'http_username='.urlencode($this->login).'&http_password='.urlencode($this->password);
      $xml = $this->xmlRequest("http_credit.asp", $datastr); 
      $saldo = (string)$xml;
    } catch (Exception $ex) {
      $saldo = $ex->getMessage();
    }
    return $saldo;
  }

  public function xmlRequest($script, $data)
  {
    $pos = strpos($data, '&format=XML'); 
    if ($pos === false) {
      $data = $data.'&format=XML';
    }
    $params = array('http' => array(
                'method' => 'POST',
                'content' => $data
              ));
    if ($this->optHeader !== null) {
      $params['http']['header'] = $this->optHeader;
    }
    $ctx = stream_context_create($params);
    $websmsURL = $this->url."/".$script; 
    $fp = @fopen($websmsURL, 'rb', false, $ctx);
    if (!$fp) {
      throw new Exception($php_errormsg);
    }
    $response = @stream_get_contents($fp);
    if ($response === false) {
      throw new Exception("Problem reading data from $this->url, $php_errormsg");
    } 
    $xml = new SimpleXMLElement($response);
    return $xml;
  }
}
?>