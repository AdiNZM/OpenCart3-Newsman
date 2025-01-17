<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/library/Newsman/Client.php");

//Admin Controller
class ControllerExtensionModuleNewsman extends Controller
{
	private $error = array();

	private $restCall = "https://ssl.newsman.app/api/1.2/rest/{{userid}}/{{apikey}}/{{method}}";
	private $restCallParams = "https://ssl.newsman.app/api/1.2/rest/{{userid}}/{{apikey}}/{{method}}{{params}}";

	public
	function index()
	{
		error_reporting(0);

		$this->document->addStyle('./view/stylesheet/newsman.css');

		$this->editModule();
	}

	protected
	function editModule()
	{
		$this->load->model('setting/setting');

		$msg = "Credentials are valid";
		$error = false;

		$setting = $this->model_setting_setting->getSetting('newsman');

		$data = array();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$data["button_save"] = "Save / Import";
		$data["message"] = "";

		if (isset($_POST["newsmanSubmit"]))
		{
			$this->restCall = "https://ssl.newsman.app/api/1.2/rest/{{userid}}/{{apikey}}/{{method}}";
			$this->restCall = str_replace("{{userid}}", $_POST["userid"], $this->restCall);
			$this->restCall = str_replace("{{apikey}}", $_POST["apikey"], $this->restCall);
			$this->restCall = str_replace("{{method}}", "list.all.json", $this->restCall);

			$settings = $setting;
			$settings["newsmanuserid"] = $_POST["userid"];
			$settings["newsmanapikey"] = $_POST["apikey"];

			$allowAPI = "off";

			if(!empty($_POST["allowAPI"]))
				$allowAPI = "on";
		
			$settings["newsmanallowAPI"] = $allowAPI;

			$this->model_setting_setting->editSetting('newsman', $settings);

			try{
				$_data = file_get_contents($this->restCall);
			
				if($_data != false)
					$_data = json_decode($_data, true);
				else
					$error = true;
			}
			catch(Exception $e)
			{
				$msg = "An error occurred, credentials might not be valid.";
				$error = true;
			}

			$data["list"] = "";

			if(!$error)
			{
				foreach ($_data as $list)
				{
					$data["list"] .= "<option value='" . $list["list_id"] . "'>" . $list["list_name"] . "</option>";
				}
			}

			$data["message"] = $msg;
		}

		if (isset($_POST["newsmanSubmitSaveList"]))
		{
			$settings = $setting;
			$settings["newsmanlistid"] = $_POST["list"];

			$this->model_setting_setting->editSetting('newsman', $settings);

			$data["message"] = "List is saved";
		}

		if (isset($_POST["newsmanSubmitSaveSegment"]))
		{
			$settings = $setting;
			$settings["newsmansegment"] = $_POST["segment"];

			$this->model_setting_setting->editSetting('newsman', $settings);

			$data["message"] = "Segment is saved";
		}

		//List Import
		if (isset($_POST["newsmanSubmitList"]))
		{
			$this->restCallParams = "https://ssl.newsman.app/api/1.2/rest/{{userid}}/{{apikey}}/{{method}}{{params}}";
			$this->restCallParams = str_replace("{{userid}}", $setting["newsmanuserid"], $this->restCallParams);
			$this->restCallParams = str_replace("{{apikey}}", $setting["newsmanapikey"], $this->restCallParams);
			$this->restCallParams = str_replace("{{method}}", "import.csv.json", $this->restCallParams);
			$client = new Newsman_Client($setting["newsmanuserid"], $setting["newsmanapikey"]);
            
			$csvdata = array();
			$this->load->model('customer/customer');
			$csvdata = $this->model_customer_customer->getCustomers();
            
			if (empty($csvdata))
			{
				$data["message"] .= PHP_EOL . "No customers in your store";
				$this->SetOutput($data);
				return;
			}

			//Import
			$batchSize = 9999;
			$customers_to_import = array();
			$segments = null;
			if ($setting["newsmansegment"] != "1" && $setting["newsmansegment"] != null)
			{
				$segments = array($setting["newsmansegment"]);
			}

			foreach ($csvdata as $item)
			{	
				if($item["newsletter"] == "0")
				{
					continue;
				}

				$customers_to_import[] = array(
					"email" => $item["email"],
					"firstname" => $item["firstname"],
					"lastname" => $item["lastname"]
				);			

				if ((count($customers_to_import) % $batchSize) == 0)
				{
					$this->_importData($customers_to_import, $setting["newsmanlistid"], $segments, $client);
				}
			}
			if (count($customers_to_import) > 0)
			{
				$this->_importData($customers_to_import, $setting["newsmanlistid"], $segments, $client);
			}	
			
			unset($customers_to_import);

			$data["message"] .= PHP_EOL . "Customer Newsletter subscribers imported successfully";
		}
		//List Import

		$setting = $this->model_setting_setting->getSetting('newsman');
		if (!empty($setting["newsmanuserid"]) && !empty($setting["newsmanapikey"]) && $error == false)
		{
			$this->restCall = str_replace("{{userid}}", $setting["newsmanuserid"], $this->restCall);
			$this->restCall = str_replace("{{apikey}}", $setting["newsmanapikey"], $this->restCall);
			$this->restCall = str_replace("{{method}}", "list.all.json", $this->restCall);
			$this->restCallParams = "https://ssl.newsman.app/api/1.2/rest/{{userid}}/{{apikey}}/{{method}}{{params}}";
			$this->restCallParams = str_replace("{{userid}}", $setting["newsmanuserid"], $this->restCallParams);
			$this->restCallParams = str_replace("{{apikey}}", $setting["newsmanapikey"], $this->restCallParams);

            try{
                $_data = file_get_contents($this->restCall);
            
                if($_data != false)
                    $_data = json_decode($_data, true);
                else
                    $error = true;
            }
			catch(Exception $e){
				$msg = "An error occurred, credentials might not be valid.";
				$error = true;
			}

			$data["list"] = "";
			$data["segment"] = "";
			$data["segment"] .= "<option value='1'>No segment</option>";
            
			if(!$error)
			{
				foreach ($_data as $list)
				{
					if (!empty($setting["newsmanlistid"]) && $setting["newsmanlistid"] == $list["list_id"])
					{
						$data["list"] .= "<option selected value='" . $list["list_id"] . "'>" . $list["list_name"] . "</option>";
						$this->restCallParams = str_replace("{{method}}", "segment.all.json", $this->restCallParams);
						$this->restCallParams = str_replace("{{params}}", "?list_id=" . $setting["newsmanlistid"], $this->restCallParams);
                        
						$_data = json_decode(file_get_contents($this->restCallParams), true);
                        
						foreach ($_data as $segment)
						{
							if (!empty($setting["newsmansegment"]) && $setting["newsmansegment"] == $segment["segment_id"])
							{
								$data["segment"] .= "<option selected value='" . $segment["segment_id"] . "'>" . $segment["segment_name"] . "</option>";
							} else
							{
								$data["segment"] .= "<option value='" . $segment["segment_id"] . "'>" . $segment["segment_name"] . "</option>";
							}
						}
					} else
					{
						$data["list"] .= "<option value='" . $list["list_id"] . "'>" . $list["list_name"] . "</option>";
					}
				}
			}
		}

		$data["userid"] = (empty($setting["newsmanuserid"])) ? "" : $setting["newsmanuserid"];
		$data["apikey"] = (empty($setting["newsmanapikey"])) ? "" : $setting["newsmanapikey"];

		$_allowAPI = "";

		if(!empty($setting["newsmanallowAPI"]) && $setting["newsmanallowAPI"] == "on"){
			$_allowAPI = "checked";
		}

		$data["allowAPI"] = $_allowAPI;

		if($error)
			$msg = "An error occurred, credentials might not be valid.";

		$data["message"] = $msg;
        
		$htmlOutput = $this->load->view('extension/module/newsman', $data);
		$this->response->setOutput($htmlOutput);
	}

	public function validate()
	{
	}

	public static function safeForCsv($str)
	{
		return '"' . str_replace('"', '""', $str) . '"';
	}

	public function _importData(&$data, $list, $segments = null, $client)
	{
        $csv = '"email","firstname","lastname","source"' . PHP_EOL;

        $source = self::safeForCsv("opencart 3 customer subscribers newsman plugin");
        foreach ($data as $_dat) {
            $csv .= sprintf(
                "%s,%s,%s,%s",
                self::safeForCsv($_dat["email"]),
                self::safeForCsv($_dat["firstname"]),
                self::safeForCsv($_dat["lastname"]),
                $source
            );
            $csv .= PHP_EOL;
		}
		
		$ret = null;
		try
		{
			$ret = $client->import->csv($list, $segments, $csv);
			if ($ret == "")
			{
				throw new Exception("Import failed");
			}
		} catch (Exception $e)
		{
		}
		$data = array();
	}

	protected function install()
	{
		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('newsman', ['newsman_status' => 1]);
	}

	protected function uninstall()
	{
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('newsman');
	}
}

?>
