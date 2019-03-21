<?
    // Klassendefinition
    class IPS2PanasonicPTLB10VE extends IPSModule 
    {
	public function Destroy() 
	{
		//Never delete this line!
		parent::Destroy();
		$this->SetTimerInterval("Messzyklus", 0);
	}
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
            	$this->RegisterPropertyBoolean("Open", false);
		
            	$this->RegisterTimer("Messzyklus", 0, 'IPS2PanasonicPTLB10VE_GetStatus($_IPS["TARGET"]);');
            	//$this->ConnectParent("{ED89906D-5B78-4D47-AB62-0BDCEB9AD330}");
		
		// Profil anlegen
		$this->RegisterProfileInteger("IPS2Panasonic.PTLB10VEStatus", "Information", "", "", 0, 2, 1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEStatus", 0, "Bereitschaft", "Information", -1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEStatus", 1, "Lampeneinschaltsteuerung", "Information", -1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEStatus", 2, "Lampe eingeschaltet", "Information", -1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEStatus", 3, "Lampenausschaltsteuerung", "Information", -1);
		
		$this->RegisterProfileInteger("IPS2Panasonic.PTLB10VEInput", "Information", "", "", 0, 2, 1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEInput", 0, "Video", "Information", -1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEInput", 1, "S-Video", "Information", -1);
		IPS_SetVariableProfileAssociation("IPS2Panasonic.PTLB10VEInput", 2, "RGB", "Information", -1);
	
		// Status-Variablen anlegen
		$this->RegisterVariableInteger("Status", "Status", "IPS2Panasonic.PTLB10VEStatus", 10);
		$this->DisableAction("Status");
		IPS_SetHidden($this->GetIDForIdent("Status"), false);
		
		$this->RegisterVariableBoolean("Power", "Power", "~Switch", 20);
		$this->EnableAction("Power");
		IPS_SetHidden($this->GetIDForIdent("Power"), false);
		
		$this->RegisterVariableInteger("Input", "Input", "IPS2Panasonic.PTLB10VEInput", 30);
		$this->EnableAction("Input");
		IPS_SetHidden($this->GetIDForIdent("Input"), false);
		
		$this->RegisterVariableInteger("Volume", "Volume", "~Intensity.255", 40);
	        $this->EnableAction("Volume");
		IPS_SetHidden($this->GetIDForIdent("Volume"), false);
        }
	
	public function GetConfigurationForm() 
	{ 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 200, "icon" => "error", "caption" => "Kommunikation gestört!");
		
		$arrayElements = array(); 
		$arrayElements[] = array("type" => "CheckBox", "name" => "Open", "caption" => "Aktiv"); 
		
  		
		
		$arrayActions = array();
		If ($this->ReadPropertyBoolean("Open") == true) {
					}
		else {
			$arrayActions[] = array("type" => "Label", "label" => "Diese Funktionen stehen erst nach Eingabe und Übernahme der erforderlichen Daten zur Verfügung!");
		}
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements, "actions" => $arrayActions)); 		 
 	}      
	    
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
	        // Diese Zeile nicht löschen
	      	parent::ApplyChanges();
		
		
		// Summary setzen
		//$this->SetSummary("GPIO RxD: ".$this->ReadPropertyInteger("Pin_RxD")." GPIO TxD: ".$this->ReadPropertyInteger("Pin_TxD"));
		
        	If ((IPS_GetKernelRunlevel() == 10103) AND ($this->HasActiveParent() == true)) {
			// den Handle für dieses Gerät ermitteln
			If (($this->ReadPropertyInteger("Pin_RxD") >= 0) AND ($this->ReadPropertyInteger("Pin_TxD") >= 0) AND ($this->ReadPropertyBoolean("Open") == true) ) {
				
				$this->SetTimerInterval("Messzyklus", 5 * 1000);
				$this->SetStatus(102);
			}
			else {
				$this->SetTimerInterval("Messzyklus", 0);
				$this->SetStatus(104);
			}
		}
        }
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
	        case "Power":
	            	$this->SendDebug("RequestAction", "Power: Ausfuehrung", 0);
			If (GetValueInteger($this->GetIDForIdent("Status")) == 0) {
				$this->Send("PON");
			}
			elseif (GetValueInteger($this->GetIDForIdent("Status")) == 2) {
				$this->Send("POF");
			}
	            	break;
	        case "Input":
			$this->SendDebug("RequestAction", "Input: Ausfuehrung", 0);
	            	$Input = array("VID", "SVD", "RG1");
			$this->Send("IIS:".$Input[$Value]);
			SetValueInteger($this->GetIDForIdent("Input"), $Value);
	           	break;
		case "Volume":
			$this->SendDebug("RequestAction", "Volume: Ausfuehrung", 0);
	            	$Volume = sprintf('%03s',intval($Value / 4));
			$this->Send("AVL:".$Volume);
			SetValueInteger($this->GetIDForIdent("Volume"), $Value);
	            	break;	
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	
	public function ReceiveData($JSONString) 
	{
		$data = json_decode($JSONString);
	 	switch ($data->Function) {
			 
	 	}
 	}
	// Beginn der Funktionen
	public function GetData()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->SendDebug("GetData", "Ausfuehrung", 0);
			IPS_Sleep(50); // Damit alle Daten auch da sind
			/*
			$Result = $this->SendDataToParent(json_encode(Array("DataID"=> "{A0DAAF26-4A2D-4350-963E-CC02E74BD414}", "Function" => "read_bb_serial", "Pin_RxD" => $this->ReadPropertyInteger("Pin_RxD") )));
			If (!$Result) {
				$this->SendDebug("GetData", "Lesen des Dateneingangs nicht erfolgreich!", 0);
				$this->SetStatus(202);
			}
			else {
				$this->SetStatus(102);
				$ByteMessage = array();
				$ByteMessage = unpack("C*", $Result);
				$this->SendDebug("GetData", $Result, 0);
				//$this->SendDebug("GetData", count($ByteMessage), 0);
				$this->SendDebug("GetData", serialize($ByteMessage), 0);
				
			}
			*/
		}
	}	
	    
	    
	public function Send(String $Message)
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->SendDebug("Send", "Ausfuehrung", 0);
			$Message = chr(2).$Message.chr(3);
			
			/*
			$MessageArray = array();
			$MessageArray = unpack("C*", $Message);
			//$Message = utf8_encode($Message);
			//$this->SendDataToParent(json_encode(Array("DataID"=> "{A0DAAF26-4A2D-4350-963E-CC02E74BD414}", "Function" => "write_bb_bytes_serial", "Baud" => 9600, "Pin_TxD" => $this->ReadPropertyInteger("Pin_TxD"), "Command" => $Message)));
			//$this->SendDataToParent(json_encode(Array("DataID"=> "{A0DAAF26-4A2D-4350-963E-CC02E74BD414}", "Function" => "write_bb_bytesarray_serial", "Baud" => 9600, "Pin_TxD" => $this->ReadPropertyInteger("Pin_TxD"), "Command" => serialize($MessageArray) )));
			*/
			$this->GetData();
		}
	}
	
	public function GetStatus()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->SendDebug("GetStatus", "Ausfuehrung", 0);
			$this->Send('Q$S');
		}
	}				
	    
	private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 1);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 1)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);        
	}    
	    
	private function HasActiveParent()
    	{
		$Instance = @IPS_GetInstance($this->InstanceID);
		if ($Instance['ConnectionID'] > 0)
		{
			$Parent = IPS_GetInstance($Instance['ConnectionID']);
			if ($Parent['InstanceStatus'] == 102)
			return true;
		}
        return false;
    	}  
    
}
?>
