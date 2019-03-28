<?php if(!defined('SYSTEM')) exit('No direct script access allowed');

if (!class_exists('Xmpphp_xmpp')) message('You must load the Xmpphp_xmpp class before loading the Xmpphp_bosh class in order to create a xmpp client.');

class Xmpphp_bosh extends Xmpphp_xmpp
{
	protected $rid;
	protected $sid;
	protected $http_server;
	protected $http_buffer = Array();
	protected $session = FALSE;

	public function connect($server, $wait = '1', $session = FALSE)
	{
		$this->http_server = $server;
		$this->use_encryption = FALSE;
		$this->session = $session;

		$this->rid = 3001;
		$this->sid = NULL;

		if($session) $this->loadSession();

		if(!$this->sid)
		{
			$body = $this->__buildBody();
			$body->addAttribute('hold','1');
			$body->addAttribute('to', $this->host);
			$body->addAttribute('route', "xmpp:{$this->host}:{$this->port}");
			$body->addAttribute('secure','true');
			$body->addAttribute('xmpp:version','1.6', 'urn:xmpp:xbosh');
			$body->addAttribute('wait', strval($wait));
			$body->addAttribute('ack','1');
			$body->addAttribute('xmlns:xmpp','urn:xmpp:xbosh');
			$buff = "<stream:stream xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams'>";
			xml_parse($this->parser, $buff, FALSE);
			$response = $this->__sendBody($body);
			$rxml = new SimpleXMLElement($response);
			$this->sid = $rxml['sid'];
		}
		else
		{
			$buff = "<stream:stream xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams'>";
			xml_parse($this->parser, $buff, FALSE);
		}
	}

	public function __sendBody($body = NULL, $recv = TRUE)
	{
		if(!$body)
		{
			$body = $this->__buildBody();
		}
		$ch = curl_init($this->http_server);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body->asXML());
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		$header = array('Accept-Encoding: gzip, deflate','Content-Type: text/xml; charset=utf-8');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header );
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		$output = '';
		if($recv)
		{
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$output = curl_exec($ch);
			$this->http_buffer[] = $output;
		}
		curl_close($ch);
		return $output;
	}

	public function __buildBody($sub = NULL)
	{
		$xml = new SimpleXMLElement("<body xmlns='http://jabber.org/protocol/httpbind' xmlns:xmpp='urn:xmpp:xbosh' />");
		$xml->addAttribute('content', 'text/xml; charset=utf-8');
		$xml->addAttribute('rid', $this->rid);
		$this->rid += 1;
		if($this->sid) $xml->addAttribute('sid', $this->sid);
		//if($this->sid) $xml->addAttribute('xmlns', 'http://jabber.org/protocol/httpbind');
		$xml->addAttribute('xml:lang', 'en');
		if($sub)
		{
			// ok, so simplexml is lame
			$p = dom_import_simplexml($xml);
			$c = dom_import_simplexml($sub);
			$cn = $p->ownerDocument->importNode($c, TRUE);
			$p->appendChild($cn);
			$xml = simplexml_import_dom($p);
		}
		return $xml;
	}

	public function __process()
	{
		if($this->http_buffer)
		{
			$this->__parseBuffer();
		}
		else
		{
			$this->__sendBody();
			$this->__parseBuffer();
		}
	}

	public function __parseBuffer()
	{
		while($this->http_buffer)
		{
			$idx = key($this->http_buffer);
			$buffer = $this->http_buffer[$idx];
			unset($this->http_buffer[$idx]);
			if($buffer)
			{
				$xml = new SimpleXMLElement($buffer);
				$children = $xml->xpath('child::node()');
				foreach ($children as $child)
				{
					$buff = $child->asXML();
					$this->log->log("RECV: $buff",  XMPPHP_Log::LEVEL_VERBOSE);
					xml_parse($this->parser, $buff, FALSE);
				}
			}
		}
	}

	public function send($msg)
	{
		$this->log->log("SEND: $msg",  XMPPHP_Log::LEVEL_VERBOSE);
		$msg = new SimpleXMLElement($msg);
		//$msg->addAttribute('xmlns', 'jabber:client');
		$this->__sendBody($this->__buildBody($msg), TRUE);
		//$this->__parseBuffer();
	}

	public function reset()
	{
		$this->xml_depth = 0;
		unset($this->xmlobj);
		$this->xmlobj = array();
		$this->setupParser();
		#$this->send($this->stream_start);
		$body = $this->__buildBody();
		$body->addAttribute('to', $this->host);
		$body->addAttribute('xmpp:restart', 'true', 'urn:xmpp:xbosh');
		$buff = "<stream:stream xmlns='jabber:client' xmlns:stream='http://etherx.jabber.org/streams'>";
		$response = $this->__sendBody($body);
		$this->been_reset = TRUE;
		xml_parse($this->parser, $buff, FALSE);
	}

	public function loadSession()
	{
		if(isset($_SESSION['XMPPHP_BOSH_RID'])) $this->rid = $_SESSION['XMPPHP_BOSH_RID'];
		if(isset($_SESSION['XMPPHP_BOSH_SID'])) $this->sid = $_SESSION['XMPPHP_BOSH_SID'];
		if(isset($_SESSION['XMPPHP_BOSH_authed'])) $this->authed = $_SESSION['XMPPHP_BOSH_authed'];
		if(isset($_SESSION['XMPPHP_BOSH_jid'])) $this->jid = $_SESSION['XMPPHP_BOSH_jid'];
		if(isset($_SESSION['XMPPHP_BOSH_fulljid'])) $this->fulljid = $_SESSION['XMPPHP_BOSH_fulljid'];
	}

	public function saveSession()
	{
		$_SESSION['XMPPHP_BOSH_RID'] = (string) $this->rid;
		$_SESSION['XMPPHP_BOSH_SID'] = (string) $this->sid;
		$_SESSION['XMPPHP_BOSH_authed'] = (boolean) $this->authed;
		$_SESSION['XMPPHP_BOSH_jid'] = (string) $this->jid;
		$_SESSION['XMPPHP_BOSH_fulljid'] = (string) $this->fulljid;
	}
}

?>