<?php

include_once(dirname(dirname(__FILE__)) . '/interfaces/interface.WebService.php');

/**
 * sp_WebService - This class handles all web services using RESTful
 *
 * @package SubjectsPlus API
 * @author dgonzalez
 * @copyright Copyright (c) 2012
 * @version $Id$
 * @date November 2012
 * @access public
 */

class sp_WebServiceHandler
{
	protected $mstrMethod = '';
	protected $mstrService = '';
	protected $mobjUrlParams = array();
	protected $mobjDBConnector = null;
	protected $mobjWebService = null;

	/**
	 * sp_WebServiceHandler::__construct() - setup properties
	 *
	 * @param string $uname
	 * @param string $pword
	 * @param string $dbName_SPlus
	 * @param string $hname
	 */
	function __construct($uname, $pword, $dbName_SPlus, $hname)
	{
		$this->mstrMethod = $_SERVER['REQUEST_METHOD'];

		$lobjTemp = explode('/', $_SERVER['REQUEST_URI']);

		for($i = (count($lobjTemp) - 1); $i >= 0; $i--)
		{
			if(strtolower($lobjTemp[$i]) == 'api')
			{
				$this->mstrService = $lobjTemp[$i + 1];

				$lobjTemp = array_slice(explode('/', $_SERVER['REQUEST_URI']), $i + 2);

				break;
			}
		}

		for($i = 0; $i < count($lobjTemp); $i = $i + 2)
		{
			if(isset($lobjTemp[$i + 1]))
			{
				$this->mobjUrlParams[strtolower($lobjTemp[$i])] = $lobjTemp[$i + 1];
			}
		}

		try {
			$this->mobjDBConnector = new sp_DBConnector($uname, $pword, $dbName_SPlus, $hname);
		} catch (Exception $e) {
			die($e);
		}
	}

	/**
	 * sp_WebServiceHandler::doService() - execute web service, whether get, post, put, or delete
	 *
	 * This method determines what service is required and if service is not supported,
	 * documentation is provided.
	 *
	 * @return void
	 */
	public function doService()
	{
		if($this->mstrService == '')
		{
			$this->displayDocumentation();
			exit;
		}

		$lstrClass = ucwords($this->mstrService). 'WebService';

		if(file_exists(dirname(__FILE__) . '/' . $lstrClass . '.php'))
		{
			include_once(dirname(__FILE__) . '/' . $lstrClass . '.php');

			$lobjWebService = new $lstrClass($this->mobjUrlParams, $this->mobjDBConnector);
		}else{
			$this->displayDocumentation(true);
			exit;
		}

		switch($this->mstrMethod)
		{
			case "GET":
				$lobjWebService->setData();
				$lobjWebService->formatOutput();
				$this->mobjWebService = $lobjWebService;
				break;
			default:
				echo "only GET Request Supported";
				exit;
		}
	}

	/**
	 * sp_WebServiceHandler::displayOutput() - echo outs web service output and
	 * determine appropriate header for the format type
	 *
	 * @return void
	 */
	public function displayOutput()
	{
		$lstrFormat = $this->mobjWebService->getFormat();

		switch($lstrFormat)
		{
			case "xml":
				header('Content-type: text/xml');
				echo $this->mobjWebService->getOutput();
				break;
			case "json":
				header('Content-type: application/json');
				echo $this->mobjWebService->getOutput();
				break;
			default:
				header('Content-type: application/json');
				echo $this->mobjWebService->getOutput();
				break;
		}
	}

	/**
	 * sp_WebServiceHandler::displayDocumentation() - display documentation for web service
	 * and depending on parameter, if http code for bad request is added.
	 *
	 * @param boolean $lboolBadRequest
	 * @return void
	 */
	public function displayDocumentation($lboolBadRequest = false)
	{
		if($lboolBadRequest)
		{
			header("HTTP/1.1 400 Bad Request");

			print "<h1>Bad Request! Here's how you use this thing.</h1>\n";
		}else
		{
			print "<h1>No service selected.  Here's how you use this thing.</h1>";
		}

		print "<pre>You Can Query Like This:\n/sp/api/service/parameter-name/parameter-value\n\n";
		print "Results can be returned as xml or json (default).  E.g.:\nsp/api/staff/output/xml\n\n";
		print "staff\n  * enter email address to return results.  Separate multiple addresses with commas.  Examples:\n";
		print "  sp/api/staff/email/you@miami.edu\n  sp/api/staff/email/you@miami.edu,me@miami.edu\n";
		print "  * select a department by id\n  sp/api/staff/department/99\n  * set a limit\n  sp/api/staff/department/99/max/5\n\n";
		print "talkback\n  * enter max number of returns\n  sp/api/talkback/max/10\n\ndatabase\n  * Lots of options:\n";
		print "  sp/api/database/letter/A -- show items beginning with A\n  sp/api/database/letter/Num -- show numbers\n";
		print "  sp/api/database/search/Science -- show all items with Science in title\n";
		print "  sp/api/database/subject_id/10 -- show all databases associated with that subject id\n";
		print "  sp/api/database/type/Reference -- show all items with that ctag\n\n";
		print "  * enter max number of returns\n  sp/api/database/type/Reference/max/10";
		print "\n\nguides\n* Lots of options:\n";
		print "  sp/api/guides/subject_id/22 -- show all guides associated with that subject id\n";
		print "  sp/api/guides/shortform/Nursing -- show all guides associated with that shortform\n";
		print "  sp/api/guides/type/Subject -- show all guides of that type\n";
		print "\n  * enter max number of returns\n  sp/api/guides/type/Subject/max/10\n\nfaq\n  * coming soon";
		print "\n\n\n  * If web service is not working correctly, the most common problem is that the .htaccess file has the wrong 'RewriteBase' path.";
		print "\n    It should reflect the path that is after your websites url. E.g. if you have www.mywebsite.com/dir1/sp/api then .htaccess file should have 'RewriteBase' path of /dir1/sp/api/";
		print "</pre>";
	}
}

?>