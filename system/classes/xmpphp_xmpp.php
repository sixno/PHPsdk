<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

class Xmpphp_xmpp extends XMPPHP_XMLStream
{
	public $server          = NULL;
	public $user;
	public $password;
	public $resource;
	public $fulljid;
	public $basejid;
	public $authed          = FALSE;
	public $session_started = FALSE;
	public $auto_subscribe  = FALSE;
	public $use_encryption  = TRUE;
	public $track_presence  = TRUE;
	public $roster;

	public function __construct($host = NULL, $port = NULL, $user = NULL, $password = NULL, $resource = NULL, $server = NULL, $printlog = FALSE, $loglevel = NULL)
	{
		if(is_array($host))
		{
			$para = $host;
			if(isset($para['host']))     $host     = $para['host'];
			if(isset($para['port']))     $port     = $para['port'];
			if(isset($para['user']))     $user     = $para['user'];
			if(isset($para['server']))   $server   = $para['server'];
			if(isset($para['password'])) $password = $para['password'];
			if(isset($para['resource'])) $resource = $para['resource'];
			if(isset($para['printlog'])) $printlog = $para['printlog'];
			if(isset($para['loglevel'])) $loglevel = $para['loglevel'];
			unset($para);
		}

		parent::__construct($host, $port, $printlog, $loglevel);
		
		$this->server   = $server;
		$this->user     = $user;
		$this->password = $password;
		$this->resource = $resource;
		$this->basejid  = $this->user.'@'.$this->host;

		$this->roster = new Roster();
		$this->track_presence = true;

		$this->initialize();

		$this->addXPathHandler('{http://etherx.jabber.org/streams}features', 'features_handler');
		$this->addXPathHandler('{urn:ietf:params:xml:ns:xmpp-sasl}success', 'sasl_success_handler');
		$this->addXPathHandler('{urn:ietf:params:xml:ns:xmpp-sasl}failure', 'sasl_failure_handler');
		$this->addXPathHandler('{urn:ietf:params:xml:ns:xmpp-tls}proceed', 'tls_proceed_handler');
		$this->addXPathHandler('{jabber:client}message', 'message_handler');
		$this->addXPathHandler('{jabber:client}presence', 'presence_handler');
		$this->addXPathHandler('iq/{jabber:iq:roster}query', 'roster_iq_handler');
	}

	public function initialize($config = array())
	{
		foreach($config as $key => $val)
		{
			$this->$key = $val;
		}

		$this->stream_start = '<stream:stream to="'.$this->server.'" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client" version="1.0">';
		$this->stream_end   = '</stream:stream>';
		$this->default_ns   = 'jabber:client';
	}

	public function useEncryption($useEncryption = true)
	{
		$this->use_encryption = $useEncryption;
	}

	public function autoSubscribe($autoSubscribe = true)
	{
		$this->auto_subscribe = $autoSubscribe;
	}

	// public function message($to, $body, $type = 'chat', $subject = NULL, $payload = NULL)
	// {
	// 	if(is_NULL($type))
	// 	{
	// 		$type = 'chat';
	// 	}
		
	// 	$to	  = htmlspecialchars($to);
	// 	$body	= htmlspecialchars($body);
	// 	$subject = htmlspecialchars($subject);
		
	// 	$out = "<message from=\"{$this->fulljid}\" to=\"$to\" type='$type'>";
	// 	if($subject) $out .= "<subject>$subject</subject>";
	// 	$out .= "<body>$body</body>";
	// 	if($payload) $out .= $payload;
	// 	$out .= "</message>";

	// 	return $this->send($out);
	// }

	public function message($to, $body, $type = 'chat', $subarray = NULL, $payload = NULL)
	{
		if(empty($type)) $type = 'chat';

		$sub_attr = '';
		if(is_array($subarray))
		{
			if(array_key_exists('@subject',$subarray))
			{
				$subject = $subarray['@subject'];
				unset($subarray['@subject']);
			}

			foreach($subarray as $key => $value)
			{
				$sub_attr .= ' '.$key.'="'.$value.'"';
			}
		}
		else
		{
			$subject = $subarray;
		}

		$body = htmlentities($body);

		$out = '<message from="'.$this->fulljid.'" to="'.$to.'" type="'.$type.'"'.$sub_attr.'>';
		if(!empty($subject)) $out .= '<subject>'.$subject.'</subject>';
		$out .= '<body>'.$body.'</body>';
		if(!empty($payload)) $out .= $payload;
		$out .= '</message>';

		return $this->send($out);
	}

	public function presence($status = NULL, $show = 'available', $to = NULL, $type = 'available', $priority = 0)
	{
		if($type == 'available') $type = '';
		$to	 = htmlspecialchars($to);
		$status = htmlspecialchars($status);
		if($show == 'unavailable') $type = 'unavailable';
		
		$out = "<presence";
		if($to) $out .= " to=\"$to\"";
		if($type) $out .= " type='$type'";
		if($show == 'available' and !$status) {
			$out .= "/>";
		} else {
			$out .= ">";
			if($show != 'available') $out .= "<show>$show</show>";
			if($status) $out .= "<status>$status</status>";
			if($priority) $out .= "<priority>$priority</priority>";
			$out .= "</presence>";
		}
		
		$this->send($out);
	}

	public function subscribe($jid)
	{
		$this->send("<presence type='subscribe' to='{$jid}' from='{$this->fulljid}' />");
		//$this->send("<presence type='subscribed' to='{$jid}' from='{$this->fulljid}' />");
	}

	public function message_handler($xml)
	{
		if(isset($xml->attrs['type'])) {
			$payload['type'] = $xml->attrs['type'];
		} else {
			$payload['type'] = 'chat';
		}
		$payload['from'] = $xml->attrs['from'];
		$payload['body'] = $xml->sub('body')->data;
		$payload['xml'] = $xml;
		$this->log->log("Message: {$xml->sub('body')->data}", XMPPHP_Log::LEVEL_DEBUG);
		$this->event('message', $payload);
	}

	public function presence_handler($xml)
	{
		$payload['type'] = (isset($xml->attrs['type'])) ? $xml->attrs['type'] : 'available';
		$payload['show'] = (isset($xml->sub('show')->data)) ? $xml->sub('show')->data : $payload['type'];
		$payload['from'] = $xml->attrs['from'];
		$payload['status'] = (isset($xml->sub('status')->data)) ? $xml->sub('status')->data : '';
		$payload['priority'] = (isset($xml->sub('priority')->data)) ? intval($xml->sub('priority')->data) : 0;
		$payload['xml'] = $xml;
		if($this->track_presence) {
			$this->roster->setPresence($payload['from'], $payload['priority'], $payload['show'], $payload['status']);
		}
		$this->log->log("Presence: {$payload['from']} [{$payload['show']}] {$payload['status']}",  XMPPHP_Log::LEVEL_DEBUG);
		if(array_key_exists('type', $xml->attrs) and $xml->attrs['type'] == 'subscribe') {
			if($this->auto_subscribe) {
				$this->send("<presence type='subscribed' to='{$xml->attrs['from']}' from='{$this->fulljid}' />");
				$this->send("<presence type='subscribe' to='{$xml->attrs['from']}' from='{$this->fulljid}' />");
			}
			$this->event('subscription_requested', $payload);
		} elseif(array_key_exists('type', $xml->attrs) and $xml->attrs['type'] == 'subscribed') {
			$this->event('subscription_accepted', $payload);
		} else {
			$this->event('presence', $payload);
		}
	}

	protected function features_handler($xml)
	{
		if($xml->hasSub('starttls') and $this->use_encryption)
		{
			$this->send("<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'><required /></starttls>");
		}
		elseif($xml->hasSub('bind') and $this->authed)
		{
			$id = $this->getId();
			$this->addIdHandler($id, 'resource_bind_handler');
			$this->send("<iq xmlns=\"jabber:client\" type=\"set\" id=\"$id\"><bind xmlns=\"urn:ietf:params:xml:ns:xmpp-bind\"><resource>{$this->resource}</resource></bind></iq>");
		}
		else
		{
			$this->log->log("Attempting Auth...");
			if ($this->password)
			{
				$this->send("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='PLAIN'>" . base64_encode("\x00" . $this->user . "\x00" . $this->password) . "</auth>");
			}
			else
			{
				$this->send("<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='ANONYMOUS'/>");
			}
		}
	}

	protected function sasl_success_handler($xml)
	{
		$this->log->log("Auth success!");
		$this->authed = true;
		$this->reset();
	}

	protected function sasl_failure_handler($xml)
	{
		$this->log->log("Auth failed!",  XMPPHP_Log::LEVEL_ERROR);
		$this->disconnect();
		
		throw new XMPPHP_Exception('Auth failed!');
	}

	protected function resource_bind_handler($xml)
	{
		if($xml->attrs['type'] == 'result')
		{
			$this->log->log("Bound to " . $xml->sub('bind')->sub('jid')->data);
			$this->fulljid = $xml->sub('bind')->sub('jid')->data;
			$jidarray = explode('/',$this->fulljid);
			$this->jid = $jidarray[0];
		}

		$id = $this->getId();
		$this->addIdHandler($id, 'session_start_handler');
		$this->send("<iq xmlns='jabber:client' type='set' id='$id'><session xmlns='urn:ietf:params:xml:ns:xmpp-session' /></iq>");
	}

	public function getRoster()
	{
		$id = $this->getID();
		$this->send("<iq xmlns='jabber:client' type='get' id='$id'><query xmlns='jabber:iq:roster' /></iq>");
	}

	protected function roster_iq_handler($xml)
	{
		$status = "result";
		$xmlroster = $xml->sub('query');
		foreach($xmlroster->subs as $item)
		{
			$groups = array();
			if ($item->name == 'item')
			{
				$jid = $item->attrs['jid']; //REQUIRED
				$name = $item->attrs['name']; //MAY
				$subscription = $item->attrs['subscription'];
				foreach($item->subs as $subitem)
				{
					if ($subitem->name == 'group')
					{
						$groups[] = $subitem->data;
					}
				}
				$contacts[] = array($jid, $subscription, $name, $groups); //Store for action if no errors happen
			}
			else
			{
				$status = "error";
			}
		}
		if($status == "result")
		{
			//No errors, add contacts
			foreach($contacts as $contact)
			{
				$this->roster->addContact($contact[0], $contact[1], $contact[2], $contact[3]);
			}
		}
		if($xml->attrs['type'] == 'set')
		{
			$this->send("<iq type=\"reply\" id=\"{$xml->attrs['id']}\" to=\"{$xml->attrs['from']}\" />");
		}
	}

	protected function session_start_handler($xml)
	{
		$this->log->log("Session started");
		$this->session_started = true;
		$this->event('session_start');
	}

	protected function tls_proceed_handler($xml)
	{
		$this->log->log("Starting TLS encryption");
		stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
		$this->reset();
	}

	public function getVCard($jid = Null)
	{
		$id = $this->getID();
		$this->addIdHandler($id, 'vcard_get_handler');
		if($jid)
		{
			$this->send("<iq type='get' id='$id' to='$jid'><vCard xmlns='vcard-temp' /></iq>");
		}
		else
		{
			$this->send("<iq type='get' id='$id'><vCard xmlns='vcard-temp' /></iq>");
		}
	}

	protected function vcard_get_handler($xml)
	{
		$vcard_array = array();
		$vcard = $xml->sub('vcard');
		// go through all of the sub elements and add them to the vcard array
		foreach($vcard->subs as $sub)
		{
			if($sub->subs)
			{
				$vcard_array[$sub->name] = array();
				foreach ($sub->subs as $sub_child)
				{
					$vcard_array[$sub->name][$sub_child->name] = $sub_child->data;
				}
			}
			else
			{
				$vcard_array[$sub->name] = $sub->data;
			}
		}
		$vcard_array['from'] = $xml->attrs['from'];
		$this->event('vcard', $vcard_array);
	}
}

class XMPPHP_XMLStream
{
	protected $socket;
	protected $parser;
	protected $buffer;
	protected $xml_depth = 0;
	protected $host;
	protected $port;
	protected $stream_start = '<stream>';
	protected $stream_end = '</stream>';
	protected $disconnected = FALSE;
	protected $sent_disconnect = FALSE;
	protected $ns_map = array();
	protected $current_ns = array();
	protected $xmlobj = NULL;
	protected $nshandlers = array();
	protected $xpathhandlers = array();
	protected $idhandlers = array();
	protected $eventhandlers = array();
	protected $lastid = 0;
	protected $default_ns;
	protected $until = '';
	protected $until_count = '';
	protected $until_happened = FALSE;
	protected $until_payload = array();
	protected $log;
	protected $reconnect = true;
	protected $been_reset = FALSE;
	protected $is_server;
	protected $last_send = 0;
	protected $use_ssl = FALSE;
	protected $reconnectTimeout = 30;

	public function __construct($host = NULL, $port = NULL, $printlog = FALSE, $loglevel = NULL, $is_server = FALSE)
	{
		$this->reconnect = !$is_server;
		$this->is_server = $is_server;
		$this->host = $host;
		$this->port = $port;
		$this->setupParser();
		$this->log = new XMPPHP_Log($printlog, $loglevel);
	}

	public function __destruct()
	{
		if(!$this->disconnected && $this->socket)
		{
			$this->disconnect();
		}
	}
	
	public function getLog()
	{
		return $this->log;
	}

	public function getId()
	{
		$this->lastid++;
		return $this->lastid;
	}

	public function useSSL($use=true)
	{
		$this->use_ssl = $use;
	}

	public function addIdHandler($id, $pointer, $obj = NULL)
	{
		$this->idhandlers[$id] = array($pointer, $obj);
	}

	public function addHandler($name, $ns, $pointer, $obj = NULL, $depth = 1)
	{
		//TODO deprication warning
		$this->nshandlers[] = array($name,$ns,$pointer,$obj, $depth);
	}

	public function addXPathHandler($xpath, $pointer, $obj = NULL)
	{
		if (preg_match_all("/\(?{[^\}]+}\)?(\/?)[^\/]+/", $xpath, $regs))
		{
			$ns_tags = $regs[0];
		}
		else
		{
			$ns_tags = array($xpath);
		}
		foreach($ns_tags as $ns_tag)
		{
			list($l, $r) = explode("}", $ns_tag);
			if($r != NULL)
			{
				$xpart = array(substr($l, 1), $r);
			}
			else
			{
				$xpart = array(NULL, $l);
			}
			$xpath_array[] = $xpart;
		}
		$this->xpathhandlers[] = array($xpath_array, $pointer, $obj);
	}

	public function addEventHandler($name, $pointer, $obj)
	{
		$this->eventhandlers[] = array($name, $pointer, $obj);
	}

	public function connect($timeout = 30, $persistent = FALSE, $sendinit = true)
	{
		$this->sent_disconnect = FALSE;
		$starttime = time();
		
		do
		{
			$this->disconnected = FALSE;
			$this->sent_disconnect = FALSE;
			if($persistent)
			{
				$conflag = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
			}
			else
			{
				$conflag = STREAM_CLIENT_CONNECT;
			}
			$conntype = 'tcp';
			if($this->use_ssl) $conntype = 'ssl';
			$this->log->log("Connecting to $conntype://{$this->host}:{$this->port}");
			try
			{
				$this->socket = @stream_socket_client("$conntype://{$this->host}:{$this->port}", $errno, $errstr, $timeout, $conflag);
			}
			catch(Exception $e)
			{
				throw new XMPPHP_Exception($e->getMessage());
			}
			if(!$this->socket)
			{
				$this->log->log("Could not connect.",  XMPPHP_Log::LEVEL_ERROR);
				$this->disconnected = true;
				// Take it easy for a few seconds
				sleep(min($timeout, 5));
			}
		}while(!$this->socket && (time() - $starttime) < $timeout);
		
		if ($this->socket)
		{
			stream_set_blocking($this->socket, 1);
			if($sendinit) $this->send($this->stream_start);

			return TRUE;
		}
		else
		{
			return FALSE;

			// throw new XMPPHP_Exception("Could not connect before timeout.");
		}
	}

	public function doReconnect()
	{
		if(!$this->is_server)
		{
			$this->log->log("Reconnecting ($this->reconnectTimeout)...",  XMPPHP_Log::LEVEL_WARNING);
			$this->connect($this->reconnectTimeout, FALSE, FALSE);
			$this->reset();
			$this->event('reconnect');
		}
	}

	public function setReconnectTimeout($timeout)
	{
		$this->reconnectTimeout = $timeout;
	}
	
	public function disconnect()
	{
		$this->log->log("Disconnecting...",  XMPPHP_Log::LEVEL_VERBOSE);
		if(FALSE == (bool) $this->socket)
		{
			return;
		}
		$this->reconnect = FALSE;
		$this->send($this->stream_end);
		$this->sent_disconnect = true;
		$this->processUntil('end_stream', 5);
		$this->disconnected = true;
	}

	public function isDisconnected()
	{
		return $this->disconnected;
	}

	private function __process($maximum=5)
	{
		
		$remaining = $maximum;
		
		do {
			$starttime = (microtime(true) * 1000000);
			$read = array($this->socket);
			$write = array();
			$except = array();
			if (is_NULL($maximum))
			{
				$secs = NULL;
				$usecs = NULL;
			}
			elseif($maximum == 0)
			{
				$secs = 0;
				$usecs = 0;
			}
			else
			{
				$usecs = $remaining % 1000000;
				$secs = floor(($remaining - $usecs) / 1000000);
			}
			$updated = @stream_select($read, $write, $except, $secs, $usecs);
			if($updated === FALSE)
			{
				$this->log->log("Error on stream_select()",  XMPPHP_Log::LEVEL_VERBOSE);				
				if($this->reconnect)
				{
					$this->doReconnect();
				}
				else
				{
					fclose($this->socket);
					$this->socket = NULL;
					return FALSE;
				}
			}
			elseif($updated > 0)
			{
				// XXX: Is this big enough?
				$buff = @fread($this->socket, 4096);
				if(!$buff)
				{ 
					if($this->reconnect)
					{
						$this->doReconnect();
					}
					else
					{
						fclose($this->socket);
						$this->socket = NULL;
						return FALSE;
					}
				}
				$this->log->log("RECV: $buff",  XMPPHP_Log::LEVEL_VERBOSE);
				xml_parse($this->parser, $buff, FALSE);
			}
			else
			{
				// $updated == 0 means no changes during timeout.
			}
			$endtime = (microtime(true)*1000000);
			$time_past = $endtime - $starttime;
			$remaining = $remaining - $time_past;
		}while (is_NULL($maximum) || $remaining > 0);

		return true;
	}
	
	
	public function process()
	{
		$this->__process(NULL);
	}

	public function processTime($timeout = NULL)
	{
		if (is_NULL($timeout))
		{
			return $this->__process(NULL);
		}
		else
		{
			return $this->__process($timeout * 1000000);
		}
	}

	public function processUntil($event, $timeout=-1)
	{
		$start = time();
		if(!is_array($event)) $event = array($event);
		$this->until[] = $event;
		end($this->until);
		$event_key = key($this->until);
		reset($this->until);
		$this->until_count[$event_key] = 0;
		$updated = '';
		while(!$this->disconnected and $this->until_count[$event_key] < 1 and (time() - $start < $timeout or $timeout == -1))
		{
			$this->__process();
		}

		if(array_key_exists($event_key, $this->until_payload))
		{
			$payload = $this->until_payload[$event_key];
			unset($this->until_payload[$event_key]);
			unset($this->until_count[$event_key]);
			unset($this->until[$event_key]);
		}
		else
		{
			$payload = array();
		}

		return $payload;
	}

	
	public function Xapply_socket($socket)
	{
		$this->socket = $socket;
	}

	public function startXML($parser, $name, $attr)
	{
		if($this->been_reset)
		{
			$this->been_reset = FALSE;
			$this->xml_depth = 0;
		}
		$this->xml_depth++;
		if(array_key_exists('XMLNS', $attr))
		{
			$this->current_ns[$this->xml_depth] = $attr['XMLNS'];
		}
		else
		{
			$this->current_ns[$this->xml_depth] = $this->current_ns[$this->xml_depth - 1];
			if(!$this->current_ns[$this->xml_depth]) $this->current_ns[$this->xml_depth] = $this->default_ns;
		}
		$ns = $this->current_ns[$this->xml_depth];
		foreach($attr as $key => $value)
		{
			if(strstr($key, ":"))
			{
				$key = explode(':', $key);
				$key = $key[1];
				$this->ns_map[$key] = $value;
			}
		}
		if(!strstr($name, ":") === FALSE)
		{
			$name = explode(':', $name);
			$ns = $this->ns_map[$name[0]];
			$name = $name[1];
		}
		$obj = new XMPPHP_XMLObj($name, $ns, $attr);
		if($this->xml_depth > 1)
		{
			$this->xmlobj[$this->xml_depth - 1]->subs[] = $obj;
		}
		$this->xmlobj[$this->xml_depth] = $obj;
	}

	public function endXML($parser, $name)
	{
		// $this->log->log("Ending $name",  XMPPHP_Log::LEVEL_DEBUG);
		// print "$name\n";
		if($this->been_reset)
		{
			$this->been_reset = FALSE;
			$this->xml_depth = 0;
		}
		$this->xml_depth--;
		if($this->xml_depth == 1)
		{
			// clean-up old objects
			// $found = FALSE; #FIXME This didn't appear to be in use --Gar
			foreach($this->xpathhandlers as $handler)
			{
				if (is_array($this->xmlobj) && array_key_exists(2, $this->xmlobj))
				{
					$searchxml = $this->xmlobj[2];
					$nstag = array_shift($handler[0]);
					if (($nstag[0] == NULL or $searchxml->ns == $nstag[0]) and ($nstag[1] == "*" or $nstag[1] == $searchxml->name))
					{
						foreach($handler[0] as $nstag)
						{
							if ($searchxml !== NULL and $searchxml->hasSub($nstag[1], $ns=$nstag[0]))
							{
								$searchxml = $searchxml->sub($nstag[1], $ns=$nstag[0]);
							}
							else
							{
								$searchxml = NULL;
								break;
							}
						}
						if ($searchxml !== NULL)
						{
							if($handler[2] === NULL) $handler[2] = $this;
							$this->log->log("Calling {$handler[1]}",  XMPPHP_Log::LEVEL_DEBUG);
							$handler[2]->$handler[1]($this->xmlobj[2]);
						}
					}
				}
			}

			foreach($this->nshandlers as $handler)
			{
				if($handler[4] != 1 and array_key_exists(2, $this->xmlobj) and  $this->xmlobj[2]->hasSub($handler[0]))
				{
					$searchxml = $this->xmlobj[2]->sub($handler[0]);
				}
				elseif(is_array($this->xmlobj) and array_key_exists(2, $this->xmlobj))
				{
					$searchxml = $this->xmlobj[2];
				}
				if($searchxml !== NULL and $searchxml->name == $handler[0] and ($searchxml->ns == $handler[1] or (!$handler[1] and $searchxml->ns == $this->default_ns)))
				{
					if($handler[3] === NULL) $handler[3] = $this;
					$this->log->log("Calling {$handler[2]}",  XMPPHP_Log::LEVEL_DEBUG);
					$handler[3]->$handler[2]($this->xmlobj[2]);
				}
			}
			foreach($this->idhandlers as $id => $handler)
			{
				if(array_key_exists('id', $this->xmlobj[2]->attrs) and $this->xmlobj[2]->attrs['id'] == $id)
				{
					if($handler[1] === NULL) $handler[1] = $this;
					$handler[1]->$handler[0]($this->xmlobj[2]);
					// id handlers are only used once
					unset($this->idhandlers[$id]);
					break;
				}
			}
			if(is_array($this->xmlobj))
			{
				$this->xmlobj = array_slice($this->xmlobj, 0, 1);
				if(isset($this->xmlobj[0]) && $this->xmlobj[0] instanceof XMPPHP_XMLObj)
				{
					$this->xmlobj[0]->subs = NULL;
				}
			}
			unset($this->xmlobj[2]);
		}
		if($this->xml_depth == 0 and !$this->been_reset)
		{
			if(!$this->disconnected)
			{
				if(!$this->sent_disconnect)
				{
					$this->send($this->stream_end);
				}
				$this->disconnected = true;
				$this->sent_disconnect = true;
				fclose($this->socket);
				if($this->reconnect)
				{
					$this->doReconnect();
				}
			}
			$this->event('end_stream');
		}
	}

	public function charXML($parser, $data)
	{
		if(array_key_exists($this->xml_depth, $this->xmlobj))
		{
			$this->xmlobj[$this->xml_depth]->data .= $data;
		}
	}

	public function event($name, $payload = NULL)
	{
		$this->log->log("EVENT: $name",  XMPPHP_Log::LEVEL_DEBUG);
		foreach($this->eventhandlers as $handler)
		{
			if($name == $handler[0])
			{
				if($handler[2] === NULL)
				{
					$handler[2] = $this;
				}
				$handler[2]->$handler[1]($payload);
			}
		}
		foreach($this->until as $key => $until)
		{
			if(is_array($until))
			{
				if(in_array($name, $until))
				{
					$this->until_payload[$key][] = array($name, $payload);
					if(!isset($this->until_count[$key]))
					{
						$this->until_count[$key] = 0;
					}
					$this->until_count[$key] += 1;
					// $this->until[$key] = FALSE;
				}
			}
		}
	}

	public function read()
	{
		$buff = @fread($this->socket, 1024);
		if(!$buff)
		{ 
			if($this->reconnect)
			{
				$this->doReconnect();
			}
			else
			{
				fclose($this->socket);
				return FALSE;
			}
		}
		$this->log->log("RECV: $buff",  XMPPHP_Log::LEVEL_VERBOSE);
		xml_parse($this->parser, $buff, FALSE);
	}

	public function send($msg, $timeout=NULL)
	{

		if (is_NULL($timeout))
		{
			$secs = NULL;
			$usecs = NULL;
		}
		elseif($timeout == 0)
		{
			$secs = 0;
			$usecs = 0;
		}
		else
		{
			$maximum = $timeout * 1000000;
			$usecs = $maximum % 1000000;
			$secs = floor(($maximum - $usecs) / 1000000);
		}
		
		$read = array();
		$write = array($this->socket);
		$except = array();
		
		$select = @stream_select($read, $write, $except, $secs, $usecs);
		
		if($select === False)
		{
			$this->log->log("ERROR sending message; reconnecting.");
			$this->doReconnect();
			// TODO: retry send here
			return FALSE;
		}
		elseif($select > 0)
		{
			$this->log->log("Socket is ready; send it.", XMPPHP_Log::LEVEL_VERBOSE);
		}
		else
		{
			$this->log->log("Socket is not ready; break.", XMPPHP_Log::LEVEL_ERROR);
			return FALSE;
		}
		
		$sentbytes = @fwrite($this->socket, $msg);
		$this->log->log("SENT: " . mb_substr($msg, 0, $sentbytes, '8bit'), XMPPHP_Log::LEVEL_VERBOSE);
		if($sentbytes === FALSE)
		{
			$this->log->log("ERROR sending message; reconnecting.", XMPPHP_Log::LEVEL_ERROR);
			$this->doReconnect();
			return FALSE;
		}
		$this->log->log("Successfully sent $sentbytes bytes.", XMPPHP_Log::LEVEL_VERBOSE);
		return $sentbytes;
	}

	public function time()
	{
		list($usec, $sec) = explode(" ", microtime());
		return (float)$sec + (float)$usec;
	}

	public function reset()
	{
		$this->xml_depth = 0;
		unset($this->xmlobj);
		$this->xmlobj = array();
		$this->setupParser();
		if(!$this->is_server)
		{
			$this->send($this->stream_start);
		}
		$this->been_reset = true;
	}

	public function setupParser()
	{
		$this->parser = xml_parser_create('UTF-8');
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'startXML', 'endXML');
		xml_set_character_data_handler($this->parser, 'charXML');
	}

	public function readyToProcess()
	{
		$read = array($this->socket);
		$write = array();
		$except = array();
		$updated = @stream_select($read, $write, $except, 0);
		return (($updated !== FALSE) && ($updated > 0));
	}
}

class Roster
{
	protected $roster_array = array();

	public function __construct($roster_array = array())
	{
		if ($this->verifyRoster($roster_array))
		{
			$this->roster_array = $roster_array; //Allow for prepopulation with existing roster
		}
		else
		{
			$this->roster_array = array();
		}
	}

	protected function verifyRoster($roster_array)
	{
		// TODO once we know *what* a valid roster array looks like
		return TRUE;
	}

	public function addContact($jid, $subscription, $name = '', $groups = array())
	{
		$contact = array('jid' => $jid, 'subscription' => $subscription, 'name' => $name, 'groups' => $groups);
		if($this->isContact($jid))
		{
			$this->roster_array[$jid]['contact'] = $contact;
		}
		else
		{
			$this->roster_array[$jid] = array('contact' => $contact);
		}
	}

	public function getContact($jid)
	{
		if ($this->isContact($jid))
		{
			return $this->roster_array[$jid]['contact'];
		}
	}

	public function isContact($jid)
	{
		return (array_key_exists($jid, $this->roster_array));
	}

	public function setPresence($presence, $priority, $show, $status)
	{
		@list($jid, $resource) = explode("/", $presence);

		if ($show != 'unavailable')
		{
			if (!$this->isContact($jid))
			{
				$this->addContact($jid, 'not-in-roster');
			}
			$resource = $resource ? $resource : '';
			$this->roster_array[$jid]['presence'][$resource] = array('priority' => $priority, 'show' => $show, 'status' => $status);
		}
		else
		{
			//Nuke unavailable resources to save memory
			unset($this->roster_array[$jid]['resource'][$resource]);
		}
	}

	public function getPresence($jid)
	{
		$split = split("/", $jid);
		$jid = $split[0];
		if($this->isContact($jid))
		{
			$current = array('resource' => '', 'active' => '', 'priority' => -129, 'show' => '', 'status' => ''); //Priorities can only be -128 = 127
			foreach($this->roster_array[$jid]['presence'] as $resource => $presence)
			{
				//Highest available priority or just highest priority
				if($presence['priority'] > $current['priority'] and (($presence['show'] == "chat" or $presence['show'] == "available") or ($current['show'] != "chat" or $current['show'] != "available")))
				{
					$current = $presence;
					$current['resource'] = $resource;
				}
			}
			return $current;
		}
	}

	public function getRoster()
	{
		return $this->roster_array;
	}
}

class XMPPHP_Exception extends Exception
{
}

class XMPPHP_Log
{
	
	const LEVEL_ERROR   = 0;
	const LEVEL_WARNING = 1;
	const LEVEL_INFO	= 2;
	const LEVEL_DEBUG   = 3;
	const LEVEL_VERBOSE = 4;
	protected $data = array();
	protected $names = array('ERROR', 'WARNING', 'INFO', 'DEBUG', 'VERBOSE');
	protected $runlevel;
	protected $printout;

	public function __construct($printout = FALSE, $runlevel = self::LEVEL_INFO)
	{
		$this->printout = (boolean)$printout;
		$this->runlevel = (int)$runlevel;
	}

	public function log($msg, $runlevel = self::LEVEL_INFO)
	{
		$time = time();
		// $this->data[] = array($this->runlevel, $msg, $time);
		if($this->printout and $runlevel <= $this->runlevel)
		{
			$this->writeLine($msg, $runlevel, $time);
		}
	}

	public function printout($clear = true, $runlevel = NULL)
	{
		if($runlevel === NULL)
		{
			$runlevel = $this->runlevel;
		}
		foreach($this->data as $data)
		{
			if($runlevel <= $data[0])
			{
				$this->writeLine($data[1], $runlevel, $data[2]);
			}
		}
		if($clear)
		{
			$this->data = array();
		}
	}
	
	protected function writeLine($msg, $runlevel, $time)
	{
		//echo date('Y-m-d H:i:s', $time)." [".$this->names[$runlevel]."]: ".$msg."\n";
		echo $time." [".$this->names[$runlevel]."]: ".$msg."\n";
		flush();
	}
}

class XMPPHP_XMLObj
{
	public $name;
	public $ns;
	public $attrs = array();
	public $subs = array();
	public $data = '';

	public function __construct($name, $ns = '', $attrs = array(), $data = '')
	{
		$this->name = strtolower($name);
		$this->ns   = $ns;
		if(is_array($attrs) && count($attrs))
		{
			foreach($attrs as $key => $value)
			{
				$this->attrs[strtolower($key)] = $value;
			}
		}
		$this->data = $data;
	}

	public function printObj($depth = 0)
	{
		print str_repeat("\t", $depth) . $this->name . " " . $this->ns . ' ' . $this->data;
		print "\n";
		foreach($this->subs as $sub)
		{
			$sub->printObj($depth + 1);
		}
	}

	public function toString($str = '')
	{
		$str .= "<{$this->name} xmlns='{$this->ns}' ";
		foreach($this->attrs as $key => $value)
		{
			if($key != 'xmlns')
			{
				$value = htmlspecialchars($value);
				$str .= "$key='$value' ";
			}
		}
		$str .= ">";
		foreach($this->subs as $sub)
		{
			$str .= $sub->toString();
		}
		$body = htmlspecialchars($this->data);
		$str .= "$body</{$this->name}>";
		return $str;
	}

	public function hasSub($name, $ns = NULL)
	{
		foreach($this->subs as $sub)
		{
			if(($name == "*" or $sub->name == $name) and ($ns == NULL or $sub->ns == $ns)) return true;
		}
		return FALSE;
	}

	public function sub($name, $attrs = NULL, $ns = NULL)
	{
		// TODO attrs is ignored
		foreach($this->subs as $sub)
		{
			if($sub->name == $name and ($ns == NULL or $sub->ns == $ns))
			{
				return $sub;
			}
		}
	}
}

?>