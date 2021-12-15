<?php

class FormProcessor
{
    private $_secret_key;
    private $_recaptcha_server = 'https://www.google.com/recaptcha/api/siteverify';
    private $_captcha_field = 'recaptchaResponse';

    private $_messages = array(
        'submitted_from' => 'Form submitted from website: %s',
        'submitted_by' => 'Visitor IP address: %s',
        'too_many_submissions' => 'Too many recent submissions from this IP',
        'failed_to_send_email' => 'Failed to send email',
        'invalid_field_type' => 'Unknown field type \'%s\'.',
        'invalid_form_config' => 'Field \'%s\' has an invalid configuration.',
        'unknown_method' => 'Unknown server request method'
    );

    public function __construct($secret_key) {
        $this->_secret_key = $secret_key;
    }

    public function process($form)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die($this->_getErrorResponse($this->_messages['unknown_method']));
        }

        if ($this->checkTooManySubmissions($_SERVER['REMOTE_ADDR'])) {
            die($this->_getErrorResponse($this->_messages['too_many_submissions']));
        }

        // will die() if there are any errors
        $this->_checkRequiredFields($form);

        // will die() if there is recaptcha error
        if ($this->_secret_key) {
            $this->_checkRecaptcha($form);
        }

        // will die() if there is a send email problem
        $this->_formSubmission($form);
    }

    public function checkTooManySubmissions($ip)
    {
        $tooManySubmissions = false;
        try {
            if (in_array("sqlite", PDO::getAvailableDrivers(), TRUE)) {
                $db = new PDO('sqlite:muse-throttle-db.sqlite3');
            } else if (function_exists("sqlite_open")) {
                $db = new PDO('sqlite2:muse-throttle-db');
            } else {
                return false;
            }
        } catch(PDOException $Exception) {
            return $tooManySubmissions;
        }

        if ($db)
        {
            $res = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='Submission_History';");
            if (!$res or $res->fetchColumn() == 0)
            {
                $db->exec("CREATE TABLE Submission_History (IP VARCHAR(39), Submission_Date TIMESTAMP)");
            }
            $db->exec("DELETE FROM Submission_History WHERE Submission_Date < DATETIME('now','-2 hours')");

            $stmt = $db->prepare("INSERT INTO Submission_History (IP,Submission_Date) VALUES (:ip, DATETIME('now'))");
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            $stmt->closeCursor();

            $stmt = $db->prepare("SELECT COUNT(1) FROM Submission_History WHERE IP = :ip;");
            $stmt->bindParam(':ip', $ip);
            $stmt->execute();
            if ($stmt->fetchColumn() > 25) {
                $tooManySubmissions = true;
            }
            // Close file db connection
            $db = null;
        }
        return $tooManySubmissions;
    }

    private function _checkRequiredFields($form)
    {
        $errors = array();
        foreach ($form['fields'] as $field => $properties) {
            if (!$properties['required']) {
                continue;
            }
            if (strpos($field, ' ')) {
                $field = str_replace(' ', '_', $field);
            }
            if (!array_key_exists($field, $_REQUEST) || ($_REQUEST[$field] !== "0" && empty($_REQUEST[$field]))) {
                array_push($errors, array('field' => $field, 'message' => $properties['errors']['required']));
            } else if (!$this->_checkFieldValueFormat($field, $properties)) {
                array_push($errors, array('field' => $field, 'message' => $properties['errors']['format']));
            }
        }
        if (!empty($errors)) {
            die($this->_getErrorResponse(array('fields' => $errors)));
        }
    }

    private function _checkRecaptcha($form) {
        $response = $_REQUEST[$this->_captcha_field];
        if (empty($response)) {
            die($this->_getErrorResponse($this->_messages['failed_to_send_email']));
        }

        $data = array (
            'secret' => $this->_secret_key,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        );

        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_recaptcha_server);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $r = curl_exec($ch);
            curl_close($ch);
        } else {
            $req = "";
            foreach ($data as $key => $value) {
                $req .= $key . '=' . urlencode(stripslashes($value)) . '&';
            }
            // Cut the last '&'
            $req = substr($req, 0, strlen($req)-1);

            $r = file_get_contents($this->_recaptcha_server . '?' . $req);
        }

        $resp = json_decode($r);

        if (!$resp->success) {
            die($this->_getErrorResponse($this->_messages['failed_to_send_email']));
        }
    }

    private function _checkFieldValueFormat($field, $properties)
    {
        $value = $this->_getFormFieldValue($field, $properties);

        switch($properties['type']) {
            case 'checkbox':
            case 'tel':
            case 'string':
                // no format to validate for those fields
                return true;
            case 'email':
                return 1 == preg_match('/^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i', $value);
            default:
                die($this->_getErrorResponse(sprintf($this->_messages['invalid_field_type'], $properties['type'])));
        }
    }

    private function _getFormFieldValue($field, $properties)
    {
        $value = isset($_REQUEST[$field]) ? $_REQUEST[$field] : '';
        switch($properties['type']) {
            case 'checkbox':
                return $value ? $value : '';
            case 'string':
            case 'tel':
            case 'email':
                $returnValues = '';
                if (is_array($value)) {
                    foreach ($value as $key => $val) {
                        $returnValues .= $this->_encodeValue($val);
                        if ($key !== count($value) - 1) {
                            $returnValues .= ', ';
                        }
                    }
                } else {
                    $returnValues = $this->_encodeValue($value);
                }
                return $returnValues;
            default:
                die($this->_getErrorResponse(sprintf($this->_messages['invalid_field_type'], $properties['type'])));
        }
    }

    private function _formSubmission($form)
    {
        $emailFrom = $form['email']['from'];
        $formEmail = $emailFrom ? $emailFrom : ((array_key_exists('email', $_REQUEST) && !empty($_REQUEST['email'])) ? $this->_cleanupEmail($_REQUEST['email']) : '');
        $headers = $this->_getEmailHeaders($formEmail);

        $to = $form['email']['to'];
        $subject = $form['subject'];
        $sendIpAddress = isset($form['sendIpAddress']) ? $form['sendIpAddress'] : true;
        $message = $this->_getEmailBody($subject, $form['email_message'], $form['fields'], $sendIpAddress);

        $sent = @mail($to, $subject, $message, $headers);

        if(!$sent) {
            die($this->_getErrorResponse($this->_messages['failed_to_send_email']));
        }

        $success_data = array(
            'redirect' => $form['success_redirect']
        );
        echo $this->_getFormResponse(true, $success_data);
    }

    private function _getEmailHeaders($formEmail) {
        $headers = 'From: ' . $formEmail . PHP_EOL;
        $headers .= 'Reply-To: ' . $formEmail . PHP_EOL;
        $headers .= 'X-Mailer: PHP/' . phpversion() . PHP_EOL;
        $headers .= 'Content-type: text/html; charset=utf-8' . PHP_EOL;
        return $headers;
    }

    private function _getEmailBody($subject, $emailMsg, $fields, $sendIpAddress) {
        $message = '<html>';
        $message .= '<head><meta http-equiv="Content-Type" content="text/html;charset=UTF-8"/><title>' . $this->_encodeValue($subject) . '</title></head>';
        $styles = <<<STYLES
<style>
    th, td, caption {
        font-weight: 400;
        vertical-align: top;
        text-align: left;
    }
</style>
STYLES;

        $message .= $styles;
        $message .= '</head><body style="background-color: #f9f9f8">';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" bgcolor="#f9f9f8" width="100%">';
        $message .= '<tbody><tr><td valign="top" align="center" style="vertical-align:top">';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:22px auto; max-width:616px" width="100%">';
        $message .= '<tbody><tr><td style="vertical-align:top" valign="top">';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%" width="100%">';
        $message .= '<tbody><tr><td style="vertical-align:top; padding:22px" valign="top">';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%" width="100%">';
        $message .= '<tbody><tr><td align="center" style="vertical-align:top; padding:0 0 22px" valign="top">';
        $message .= $emailMsg;
        $message .= '</td></tr></tbody></table>';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#fff" width="100%" bgcolor="#ffffff">';
        $message .= '<tbody><tr><td align="center" style="vertical-align:top" valign="top">';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%; text-align:left" width="100%" align="left">';
        $message .= '<tbody><tr><td style="vertical-align:top; font-family:Helvetica, Arial, Verdana, sans-serif; padding:11px" valign="top">';

        $message .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%" width="100%"><tbody>';

        $sortedFields = array();

        foreach ($fields as $field => $properties) {
            array_push($sortedFields, array('field' => $field, 'properties' => $properties));
        }

        // sort fields
        usort($sortedFields, array(&$this, '_fieldComparer'));

        foreach ($sortedFields as $fieldWrapper) {
            $message .= '<tr><td style="vertical-align:top; font-family:Helvetica, Arial, Verdana, sans-serif; padding:11px; border-bottom:#e3e3da 1px solid; font-size:14px" valign="top">';
            $message .= '<strong>' . $this->_encodeValue($fieldWrapper['properties']['label']) . ':</strong>';
            $message .= '<pre style="white-space:inherit; font-family:inherit; margin:0">' . $this->_getFormFieldValue($fieldWrapper['field'], $fieldWrapper['properties']) . '</pre>';
            $message .= '</td></tr>';
        }

        $message .= '</tbody></table>';
        $message .= '</td></tr>';

        if ($sendIpAddress) {
            $message .= '<tr align="center" style="padding:0 0 22px">';
            $message .= '<td style="vertical-align:top; font-family:Helvetica, Arial, Verdana, sans-serif; padding:11px" valign="top">';
            $message .= '<div style="color:#918f8d; font-size:12px">' . sprintf($this->_messages['submitted_from'], $this->_encodeValue($_SERVER['SERVER_NAME'])) . '</div>';
            $message .= '<div style="color:#918f8d; font-size:12px">' . sprintf($this->_messages['submitted_by'], $this->_encodeValue($_SERVER['REMOTE_ADDR'])) . '</div>';
            $message .= '</td></tr>';
        }

        $message .= '</tbody></table>';
        $message .= '</td></tr></tbody></table>';
        $message .= '</td></tr></tbody></table>';
        $message .= '</td></tr></tbody></table>';
        $message .= '</td></tr></tbody></table>';
        $message .= '</body></html>';

        return $this->_cleanupMessage($message);
    }

    private function _fieldComparer($field1, $field2) {
        if ($field1['properties']['order'] == $field2['properties']['order'])
            return 0;

        return (($field1['properties']['order'] < $field2['properties']['order']) ? -1 : 1);
    }

    private function _getErrorResponse($error)
    {
        if (is_array($error)) {
            $errorMsg = '';
            foreach ($error as $err) {
                if(isset($err['message'])) {
                    $errorMsg .=  $err['message'] . "\n";
                }
            }
            $error = $errorMsg;
        }
        return $this->_getFormResponse(false, array('error' => $error));
    }

    private function _getFormResponse($success, $data)
    {
        header('Content-Type: application/json');
        $status = array_merge(array('success' => $success), $data);
        return json_encode($status);
    }

    private function _encodeValue($text)
    {
        return htmlentities(stripslashes($text), ENT_QUOTES, 'UTF-8');
    }

    private function _cleanupMessage($message) {
        $message = wordwrap($message, 70, "\r\n");
        return $message;
    }

    private function _cleanupEmail($email) {
        $email = $this->_encodeValue($email);
        $email = preg_replace('=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\n|\\r)\S).*=i', null, $email);
        return $email;
    }

    public static function startDiagnostics()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            exit;
        }

        $supportResponse = FormProcessor::checkSupport();

        if (!empty($_GET['mode']) and $_GET['mode'] == 'verify')
        {
            exit($supportResponse);
        }

        $message = '<!DOCTYPE html><html><head><title>Diagnostics</title><style type="text/css">';
        $message .= 'body { font: 14pt Arial, Helvetica;} ul { list-style-type: none; }';
        $message .= ' h1 { background-color: #f9f9f8; padding: 2px;} label {display: inline-block; width: 100px; vertical-align: top;}';
        $message .= '.good:before { color: #08af08; content:\'\2713\0020\';} .bad:before {color: red; content: \'X\0020\';}';
        $message .= '</style></head><body>';
        $message .= '<h1>Diagnostics</h1><ul>';

        if (strrpos($supportResponse,'PHP:0;') === false) {
            $message .= '<li class="bad">PHP: version too low';
        } else {
            $message .= '<li class="good">PHP: OK';
        }

        if (strrpos($supportResponse,'MAIL:0;') === false)
        {
            $message .= '<li class="bad">Mail configuration: PHP mail() configured incorrectly on server. Form will not be able to send email.';
        } else {
            $message .= '<li class="good">Mail configuration: OK';
        }

        if (strrpos($supportResponse,'SQL:1;') !== false) {
            $message .= '<li class="bad">Spam control: SQLite not found. Form may send email successfully, but limiting spam submissions by IP address will not work.';
        } else if (strrpos($supportResponse,'SQL:8;') !== false) {
            $message .= '<li class="bad">Spam control: Cannot write to scripts directory. Form may send email successfully, but limiting spam submissions by IP address will not work.';
        } else if (strrpos($supportResponse,'SQL:0;') === false) {
            $message .= '<li class="bad">Spam control: SQL configuration problem. Form may send email successfully, but limiting spam submissions by IP address will not work.';
        } else {
            $message .= '<li class="good">Spam control: OK<br /><br />Emails will be limited to 25 in 2 hours from the same IP address';
        }

        $message .= '</ul><br/><br/>';
        $message .= '</body></html>';
        exit($message);
    }

    public static function checkSupport()
    {
        $throttleSupport = FormProcessor::checkDb();
        $response ='SQL:' . $throttleSupport . ';';

        $version = explode('.', PHP_VERSION);
        if ($version[0] < 4 || ($version[0] == 4 && $version[1] < 1)) {
            $response .='PHP:1;';
            return $response;
        } else {
            $response .='PHP:0;';
        }

        if (strncasecmp(php_uname('s'), 'win', 3) == 0) {
            $mailserver = ini_get('SMTP');
        } else {
            $mailserver = ini_get('sendmail_path');
        }

        if (strlen($mailserver) == 0) {
            $response .='MAIL:1;';
        } else {
            if (!function_exists("mail")) {
                $response .='MAIL:2;';
            } else {
                $sent = mail("recipient@example.com", "Hi", "test message", "From: sender@example.com");
                if($sent) {
                    $response .='MAIL:0;';
                } else {
                    $response .='MAIL:3;';
                }
            }
        }

        return $response;
    }

    public static function checkDb()
    {
        if (!is_writable('.')) {
            return '8';
        }

        try
        {
            if (in_array("sqlite", PDO::getAvailableDrivers(), TRUE)) {
                $db = new PDO('sqlite:muse-throttle-db.sqlite3');
                if (file_exists('muse-throttle-db')) {
                    unlink('muse-throttle-db');
                }
            } else if (function_exists("sqlite_open")) {
                $db = new PDO('sqlite2:muse-throttle-db');
                if (file_exists('muse-throttle-db.sqlite3')) {
                    unlink('muse-throttle-db.sqlite3');
                }
            } else {
                return '4';
            }
        } catch (PDOException $Exception) {
            return '9';
        }

        $retCode ='5';
        if ($db) {
            $res = $db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='Submission_History';");
            if (!$res or $res->fetchColumn() == 0) {
                $created = $db->exec("CREATE TABLE Submission_History (IP VARCHAR(39), Submission_Date TIMESTAMP)");
                if($created == 0) {
                    $created = $db->exec("INSERT INTO Submission_History (IP,Submission_Date) VALUES ('256.256.256.256', DATETIME('now'))");
                }

                if ($created != 1) {
                    $retCode = '2';
                }
            }
            if($retCode == '5')
            {
                $res = $db->query("SELECT COUNT(1) FROM Submission_History;");
                if ($res && $res->fetchColumn() > 0) {
                    $retCode = '0';
                } else {
                    $retCode = '3';
                }
            }
            // Close file db connection
            $db = null;
        } else {
            $retCode = '4';
        }
        return $retCode;
    }
}