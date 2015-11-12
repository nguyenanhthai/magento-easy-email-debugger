<?php

class Nat_Localmail_Model_Rewrite_Core_Email_Template extends Mage_Core_Model_Email_Template {

  /**
   * Send mail to recipient
   *
   * @param   array|string       $email        E-mail(s)
   * @param   array|string|null  $name         receiver name(s)
   * @param   array              $variables    template variables
   * @return  boolean
   **/
  public function send($email, $name = null, array $variables = array()) {
    if (!$this->isValidForSend()) {
      Mage::logException(new Exception('This letter cannot be sent.')); // translation is intentionally omitted
      return false;
    }

    $emails = array_values((array)$email);
    $names = is_array($name) ? $name : (array)$name;
    $names = array_values($names);
    foreach ($emails as $key => $email) {
      if (!isset($names[$key])) {
        $names[$key] = substr($email, 0, strpos($email, '@'));
      }
    }

    $variables['email'] = reset($emails);
    $variables['name'] = reset($names);

    $this->setUseAbsoluteLinks(true);
    $text = $this->getProcessedTemplate($variables, true);
    $subject = $this->getProcessedTemplateSubject($variables);

    $setReturnPath = Mage::getStoreConfig(self::XML_PATH_SENDING_SET_RETURN_PATH);
    switch ($setReturnPath) {
      case 1:
        $returnPathEmail = $this->getSenderEmail();
        break;
      case 2:
        $returnPathEmail = Mage::getStoreConfig(self::XML_PATH_SENDING_RETURN_PATH_EMAIL);
        break;
      default:
        $returnPathEmail = null;
        break;
    }

    if ($this->hasQueue() && $this->getQueue() instanceof Mage_Core_Model_Email_Queue) {
      /** @var $emailQueue Mage_Core_Model_Email_Queue */
      $emailQueue = $this->getQueue();
      $emailQueue->setMessageBody($text);
      $emailQueue->setMessageParameters(array(
        'subject'           => $subject,
        'return_path_email' => $returnPathEmail,
        'is_plain'          => $this->isPlain(),
        'from_email'        => $this->getSenderEmail(),
        'from_name'         => $this->getSenderName(),
        'reply_to'          => $this->getMail()->getReplyTo(),
        'return_to'         => $this->getMail()->getReturnPath(),
      ))
        ->addRecipients($emails, $names, Mage_Core_Model_Email_Queue::EMAIL_TYPE_TO)
        ->addRecipients($this->_bccEmails, array(), Mage_Core_Model_Email_Queue::EMAIL_TYPE_BCC);
      $emailQueue->addMessageToQueue();

      return true;
    }

    ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
    ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));

    $mail = $this->getMail();

    if ($returnPathEmail !== null) {
      $mailTransport = new Zend_Mail_Transport_Sendmail("-f".$returnPathEmail);
      Zend_Mail::setDefaultTransport($mailTransport);
    }

    foreach ($emails as $key => $email) {
      $mail->addTo($email, '=?utf-8?B?' . base64_encode($names[$key]) . '?=');
    }

    if ($this->isPlain()) {
      $mail->setBodyText($text);
    } else {
      $mail->setBodyHTML($text);
    }

    $mail->setSubject('=?utf-8?B?' . base64_encode($subject) . '?=');
    $mail->setFrom($this->getSenderEmail(), $this->getSenderName());

    /* Local Mail Server */
    if ($_mode = Mage::getStoreConfig('localmail/general/mode')) {
      switch ($_mode) {
        case Nat_Localmail_Model_System_Config_Source_Mode::SHOW_CONTENT_AND_STOP:
          echo $text;
          die();
        case Nat_Localmail_Model_System_Config_Source_Mode::LOG_AS_FILE:
          try {
            $subject = str_replace(' ', '-', $subject); // Replaces all spaces with hyphens.
            $subject = preg_replace('/[^A-Za-z0-9\-]/', '', $subject); // Removes special chars.
            $subject = preg_replace('/-+/', '-', $subject);
            $_file_name = sprintf('%s_%s.html', $subject, uniqid());
            $logDir  = Mage::getBaseDir('var') . DS . 'email';
            $logFile = $logDir . DS . $_file_name;
            if (!is_dir($logDir)) {
              mkdir($logDir);
              chmod($logDir, 0750);
            }
            if (!file_exists($logFile)) {
              file_put_contents($logFile, '');
              chmod($logFile, 0640);
            }
            $writer = new Zend_Log_Writer_Stream($logFile);
            $logger = new Zend_Log();
            $logger->addWriter($writer);
            $logger->info($text);
          } catch (Exception $e) {
            var_dump($e->getMessage());
            die();
          }
          return true;
      }
    }
    /* End Local Mail Server */

    try {
      $mail->send();
      $this->_mail = null;
    }
    catch (Exception $e) {
      $this->_mail = null;
      Mage::logException($e);
      return false;
    }

    return true;
  }
}