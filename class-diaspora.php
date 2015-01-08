<?php
/**
* Based on class from https://github.com/Faldrian/WP-diaspora-postwidget/blob/master/wp-diaspora-postwidget/diasphp.php  -- Thanks, Faldrian!
*
* Ein fies zusammengehackter PHP-Diaspory-Client, der direkt von diesem abgeschaut ist:
* https://github.com/Javafant/diaspy/blob/master/client.py
*/

class Diasphp {
    function __construct($pod) {
        $this->token_regex = '/content="(.*?)" name="csrf-token/';

        $this->pod = $pod;
        $this->cookiejar = tempnam(sys_get_temp_dir(), 'cookies');
    }

    function _fetch_token() {
        $ch = curl_init();
	$max_redirects = 10;

        curl_setopt ($ch, CURLOPT_URL, $this->pod . "/stream");
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);

	if (ini_get('open_basedir') === '' && ini_get('safe_mode' === 'Off')) {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, $max_redirects);
		$output = curl_exec($ch);
	} else {
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		$mr = $max_redirects;
		if ($mr > 0) {
			$newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	
			$rcurl = curl_copy_handle($ch);
			curl_setopt($rcurl, CURLOPT_HEADER, true);
			curl_setopt($rcurl, CURLOPT_NOBODY, true);
			curl_setopt($rcurl, CURLOPT_FORBID_REUSE, false);
			curl_setopt($rcurl, CURLOPT_RETURNTRANSFER, true);
			do {
				curl_setopt($rcurl, CURLOPT_URL, $newurl);
				$header = curl_exec($rcurl);
				if (curl_errno($rcurl)) {
					$code = 0;
				} else {
					$code = curl_getinfo($rcurl, CURLINFO_HTTP_CODE);
					if ($code == 301 || $code == 302) {
						preg_match('/Location:(.*?)\n/', $header, $matches);
						$newurl = trim(array_pop($matches));
					} else {
						$code = 0;
					}
				}
			} while ($code && --$mr);
			curl_close($rcurl);
			if ($mr > 0) {
				curl_setopt($ch, CURLOPT_URL, $newurl);
			}
		}

		if($mr == 0 && $max_redirects > 0) {
			$output = false;
		} else {
			$output = curl_exec($ch);
		}
	}
        curl_close($ch);

        // Token holen und zurückgeben
        preg_match($this->token_regex, $output, $matches);
        return $matches[1];
    }

    function login($username, $password) {
        $datatopost = array(
            'user[username]' => $username,
            'user[password]' => $password,
            'authenticity_token' => $this->_fetch_token()
        );

        $poststr = http_build_query($datatopost);

        // Adresse per cURL abrufen
        $ch = curl_init();

        curl_setopt ($ch, CURLOPT_URL, $this->pod . "/users/sign_in");
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, true);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $poststr);

        curl_exec ($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if($info['http_code'] != 302) {
            throw new Exception('Login error '.print_r($info, true));
        }

        // Das Objekt zurückgeben, damit man Aurufe verketten kann.
        return $this;
    }

    function post($text, $provider = "diasphp") {
        // post-daten vorbereiten
        $datatopost = json_encode(array(
                'aspect_ids' => 'public',
                'status_message' => array('text' => $text,
                            'provider_display_name' => $provider)
        ));

        // header vorbereiten
        $headers = array(
            'Content-Type: application/json',
            'accept: application/json',
            'x-csrf-token: '.$this->_fetch_token()
        );

        // Adresse per cURL abrufen
        $ch = curl_init();

        curl_setopt ($ch, CURLOPT_URL, $this->pod . "/status_messages");
        curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt ($ch, CURLOPT_POST, true);
        curl_setopt ($ch, CURLOPT_POSTFIELDS, $datatopost);
        curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);

        curl_exec ($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if($info['http_code'] != 201) {
            throw new Exception('Post error '.print_r($info, true));
        }

        // Ende der möglichen Kette, gib mal "true" zurück.
        return true;
    }
}

?>
