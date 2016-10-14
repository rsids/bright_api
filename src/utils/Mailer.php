<?php

namespace fur\bright\utils;
use fur\bright\exceptions\GenericException;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;

/**
 * Wrapper class to send e-mails with
 * Version history:
 * 2.2 20120419
 * - Implements sendHtmlMail
 * @author Ids Klijnsma - Fur
 * @version 2.2
 * @package Bright
 * @subpackage utils
 */
class Mailer extends Permissions  {

	/**
	 * Sends an e-mail
	 * @since 2.1 Added cc, bcc, attachments
	 * @param string $from The senders e-mail address
	 * @param mixed $to The receivers e-mail address. Can be string, or an array of key value pairs, where the key is the email address and the value is the name
	 * @param string $subject The subject of the e-mail
	 * @param string $message The message of the e-mail
	 * @param mixed $cc The receivers e-mail address. Can be string, or an array of key value pairs, where the key is the email address and the value is the name
	 * @param mixed $bcc The receivers e-mail address. Can be string, or an array of key value pairs, where the key is the email address and the value is the name
	 * @param array $attachments An array of attachments
	 * @return boolean True when successful
	 */
	public function sendPlainMail($from, $to, $subject, $message, $cc = null, $bcc = null, $attachments = null) {
		return $this -> _sendMail($from, $to, $subject, $message, null, $cc, $bcc, $attachments);
	}


	/**
	 * Sends an e-mail
	 * @since 2.1 Added cc, bcc, attachments
	 * @param string $from The senders e-mail address
	 * @param mixed $to The receivers e-mail address. Can be string, or an array of key value pairs, where the key is the email address and the value is the name
	 * @param string $subject The subject of the e-mail
	 * @param string $messageHtml
	 * @param null $messagePlain
	 * @param mixed $cc The receivers e-mail address. Can be string, or an array of key value pairs, where the key is the email address and the value is the name
	 * @param mixed $bcc The receivers e-mail address. Can be string, or an array of key value pairs, where the key is the email address and the value is the name
	 * @param array $attachments An array of attachments
	 * @return boolean True when successfull
	 */
	public function sendHtmlMail($from, $to, $subject, $messageHtml, $messagePlain = null, $cc = null, $bcc = null, $attachments = null) {
		if(!$messagePlain)
			$messagePlain = $messageHtml;

		return $this -> _sendMail($from, $to, $subject, $messagePlain, $messageHtml, $cc, $bcc, $attachments);
	}

	/**
	 * Sends a mailing to a list of email addresses
	 *
	 * @param string $from The sending email address
	 * @param array $to An array of email addresses
	 * @param string $subject The e-mails subject
	 * @param string $message The message to send
	 * @param array $replacements An array of replacements
	 * @throws \Exception
	 * @todo Describe replacements;
	 */
	public function sendMassMail($from, $to, $subject, $message, $replacements = null) {
		if(!SWIFTAVAILABLE) {
			throw $this -> throwException(GenericException::SWIFT_NOT_FOUND);
		}
		require_once('Swift/lib/swift_required.php');

		$transport = null;
		if(defined('TRANSPORT') && TRANSPORT == 'smtp') {
			$transport = \Swift_SmtpTransport::newInstance(SMTP, SMTPPORT);
		} else if(TRANSPORT == 'sendmail') {
			$transport = \Swift_SendmailTransport::newInstance();
		} else {
			$transport = \Swift_MailTransport::newInstance();
		}

		$mailer = \Swift_Mailer::newInstance($transport);
		$mailer -> registerPlugin(new \Swift_Plugins_ThrottlerPlugin(50, \Swift_Plugins_ThrottlerPlugin::MESSAGES_PER_MINUTE));
		$mailer -> registerPlugin(new \Swift_Plugins_AntiFloodPlugin(100, 30));

		$plain = $message;
		$plain = str_replace('</p>', "\r\n", $plain);
		$plain = str_replace('</br>', "\r\n", $plain);
		$plain = str_replace('<br/>', "\r\n", $plain);
		$plain = str_replace('<BR>', "\r\n", $plain);
		$plain = strip_tags($plain);

		$msg = \Swift_Message::newInstance();

		//Give the message a subject
		$msg->setSubject($subject)
			->setMaxLineLength(1000)

			->setFrom($from)


			->setReturnPath(MAILINGBOUNCE)

			->setEncoder(\Swift_Encoding::get8BitEncoding())
			->setBody($message, 'text/html')

			->addPart($plain, 'text/plain');

		if($replacements) {
			$decorator = new \Swift_Plugins_DecoratorPlugin($replacements);
			$mailer -> registerPlugin($decorator);
		}



		$headers = $msg->getHeaders();
		//Content-Transfer-Encoding: 7bit
		if($headers) {
			$cte = $headers->get('Content-Transfer-Encoding');
			if($cte) {
				$cte->setValue('7bit');

			}
		}

		$failures = array();
		if(DISABLEMAIL) {
			error_log(SITENAME . ' Mail disabled');
			return;
		}

		if(!is_array($to))
			$to = array($to);
		$numSent = 0;

		foreach ($to as $address => $name) {
			if (is_int($address)) {
				$msg->setTo($name);
			} else {
				$msg->setTo(array($address => $name));
			}

			$numSent += $mailer->send($msg, $failures);
		}

		if (!$numSent) {
			error_log(SITENAME . "Batch mail had some false e-mail addresses:\r\n" .print_r($failures, true), 1, SYSMAIL);
		} else {
			error_log(SITENAME . " Sent " . $numSent . " messages");
		}
	}

	private function _sendMail($from, $to, $subject, $messagePlain, $messageHtml = null, $cc = null, $bcc = null, $attachments = null) {
		if(!SWIFTAVAILABLE) {
			throw $this -> throwException(GenericException::SWIFT_NOT_FOUND);
		}
		require_once('Swift/lib/swift_required.php');

		$subject = filter_var($subject, FILTER_SANITIZE_STRING);
		$messagePlain = filter_var($messagePlain, FILTER_SANITIZE_STRING);

		if(is_string($from)) {
			if(filter_var($from, FILTER_VALIDATE_EMAIL)) {
				$from = array($from => $from);
			} else {
				throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
			}
		} else if(is_array($from)) {
			// Only one sender
			$from = array_splice($from,0,1);
			foreach($from as $key => $val) {
				if(filter_var($key, FILTER_VALIDATE_EMAIL)) {
					$from = array($key => filter_var($val, FILTER_SANITIZE_STRING));
				} else {
					throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
				}
			}
		} else {
			throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
		}

		// Validate e-mail addresses
		$receivers = (object) array('to' => $to, 'cc' => $cc, 'bcc' => $bcc);
		foreach($receivers as &$receiver) {
			if($receiver) {
				if(is_string($receiver)) {
					if(filter_var($receiver, FILTER_VALIDATE_EMAIL)) {
						$receiver = array($receiver => $receiver);
					} else {
						throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
					}
				} else if(is_array($receiver)) {
					foreach($receiver as $key => $val) {
						if(is_array($val)) {
							foreach($val as $vkey => $vval) {
								if(filter_var($vkey, FILTER_VALIDATE_EMAIL)) {
									$receiver[$vkey] = filter_var($vval, FILTER_SANITIZE_STRING);
									unset($receiver[$key]);
								} else {
									throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
								}
							}
						} else if(filter_var($key, FILTER_VALIDATE_EMAIL)){
							// May be double, but you never know
							$receiver[$key] = filter_var($val, FILTER_SANITIZE_STRING);

						} else {
							if(filter_var($val, FILTER_VALIDATE_EMAIL)) {
								$receiver[$val] = filter_var($val, FILTER_SANITIZE_STRING);
							} else {
								throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
							}
						}
					}
				} else {
					throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
				}
			}
		}

		if(count($receivers -> to) == 0 && count($receivers -> cc) == 0 && count($receivers -> bcc) == 0) {
			throw $this -> throwException(ParameterException::EMAIL_EXCEPTION);
		}

		$transport = null;
		if(defined('TRANSPORT') && TRANSPORT == 'smtp') {
			$transport = \Swift_SmtpTransport::newInstance(SMTP, SMTPPORT);
		} else if(defined('TRANSPORT') && TRANSPORT == 'sendmail') {
			$transport = \Swift_SendmailTransport::newInstance();
		} else {
			$transport = \Swift_MailTransport::newInstance();
		}

		$mailer = \Swift_Mailer::newInstance($transport);


		$msg = \Swift_Message::newInstance();

		//Give the message a subject
		$msg->setSubject($subject)
			->setMaxLineLength(1000)
			->setFrom($from);

		if(count($receivers -> to) > 0) {
			$msg -> setTo($receivers -> to);
		} else {
			$msg -> setTo($from);

		}


		if($messageHtml != null) {
			$msg->setEncoder(\Swift_Encoding::get8BitEncoding());
			$msg->setBody($messageHtml, 'text/html');
		}
		if($messagePlain != null && $messagePlain != false) {
			$msg -> addPart($messagePlain, 'text/plain');
		}

		if($messageHtml != null) {
			$headers = $msg->getHeaders();
			if($headers) {
				$cte = $headers->get('Content-Transfer-Encoding');
				if($cte) {
					$cte->setValue('7bit');
				}
			}

		}

		if($attachments != null) {
			if(!is_array($attachments)) {
				$attachments = array($attachments);
			}
			
			foreach ($attachments as $att) {
				if(file_exists($att)) {
					$msg -> attach(\Swift_Attachment::fromPath($att));
				} else if($att instanceof \Swift_Attachment) {
					$msg -> attach($att);
				} else {
					Connection::getInstance() -> addTolog($att . ' does not exist and is therefore not attached to message with subject ' . $subject);
				}
			}
		}

		if(count($receivers -> cc) > 0) {
			$msg->setCc($receivers -> cc);
		}
		if(count($receivers -> bcc) > 0) {
			$msg->setBcc($receivers -> bcc);
		}

		if(DISABLEMAIL) {
			return count($receivers -> cc) + count($receivers -> bcc) + count($receivers -> to);
		}
		$result = $mailer->send($msg);
		return $result;
	}
}
