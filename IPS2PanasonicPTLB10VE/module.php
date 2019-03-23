<?
    // Klassendefinition
    class IPS2PanasonicPTLB10VE extends IPSModule 
    {
	private $Socket = false;
	    
	public function __destruct()
	{
		if ($this->Socket)
		    	socket_close($this->Socket);
	} 
	    
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
		$this->RegisterPropertyString("IPAddress", "127.0.0.1");
		$this->RegisterPropertyInteger("Port", 0);
		
            	$this->RegisterTimer("Messzyklus", 0, 'IPS2PanasonicPTLB10VE_GetStatus($_IPS["TARGET"]);');
  		
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
		
		$this->RegisterVariableBoolean("Power", "Power", "~Switch", 20);
		$this->EnableAction("Power");
		
		$this->RegisterVariableInteger("Input", "Input", "IPS2Panasonic.PTLB10VEInput", 30);
		$this->EnableAction("Input");
		
		$this->RegisterVariableInteger("Volume", "Volume", "~Intensity.255", 40);
	        $this->EnableAction("Volume");
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
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IPAddress", "caption" => "IP");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Port", "caption" => "Port");
		
  		
		
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
		$this->SetSummary($this->ReadPropertyString("IPAddress").":".$this->ReadPropertyInteger("Port"));
		
        	If (($this->ReadPropertyBoolean("Open") == true) AND ($this->ConnectionTest() == true)) {
			$this->SetTimerInterval("Messzyklus", 5 * 1000);
			$this->SetStatus(102);
			$this->GetStatus();
		}
		else {
			$this->SetTimerInterval("Messzyklus", 0);
			$this->SetStatus(104);
		}
        }
	    
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
	        case "Power":
	            	$this->SendDebug("RequestAction", "Power: Ausfuehrung", 0);
			If (GetValueInteger($this->GetIDForIdent("Status")) == 0) {
				$this->CommandClientSocket("PON", 5);
			}
			elseif (GetValueInteger($this->GetIDForIdent("Status")) == 2) {
				$this->CommandClientSocket("POF", 5);
			}
			$this->GetStatus();
	            	break;
	        case "Input":
			$this->SendDebug("RequestAction", "Input: Ausfuehrung", 0);
	            	$Input = array("VID", "SVD", "RG1");
			$this->CommandClientSocket("IIS:".$Input[$Value], 9);
	           	break;
		case "Volume":
			$this->SendDebug("RequestAction", "Volume: Ausfuehrung", 0);
	            	$Volume = sprintf('%03s',intval($Value / 4));
			$this->CommandClientSocket("AVL:".$Volume, 9);
	            	break;	
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	
	// Beginn der Funktionen
	private function CommandClientSocket(String $Message, $ResponseLen = 3)
	{
		$Result = -999;
		$Message = chr(2).$Message.chr(3);
		$Port = $this->ReadPropertyInteger("Port");
		If ($this->ReadPropertyBoolean("Open") == true) {
			if (!$this->Socket)
			{
				// Socket erstellen
				if(!($this->Socket = socket_create(AF_INET, SOCK_STREAM, 0))) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					$this->SendDebug("CommandClientSocket", "Fehler beim Erstellen ".$errorcode." ".$errormsg, 0);
					IPS_SemaphoreLeave("ClientSocket");
					return;
				}
				// Timeout setzen
				socket_set_option($this->Socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>2, "usec"=>0));
				// Verbindung aufbauen
				if(!(socket_connect($this->Socket, $this->ReadPropertyString("IPAddress"), $Port))) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					$this->SendDebug("CommandClientSocket", "Fehler beim Verbindungsaufbaus ".$errorcode." ".$errormsg, 0);
					IPS_SemaphoreLeave("ClientSocket");
					return;
				}
				if (!$this->Socket) {
					IPS_LogMessage("IPS2PanasonicPTLB10VE Socket", "Fehler beim Verbindungsaufbau ".$errno." ".$errstr);
					$this->SendDebug("CommandClientSocket", "Fehler beim Verbindungsaufbau ".$errno." ".$errstr, 0);
					IPS_SemaphoreLeave("ClientSocket");
					return $Result;
				}
			}
			// Message senden
			if(!socket_send ($this->Socket, $Message, strlen($Message), 0))
			{
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				IPS_LogMessage("IPS2PanasonicPTLB10VE Socket", "Fehler beim Senden ".$errorcode." ".$errormsg);
				$this->SendDebug("CommandClientSocket", "Fehler beim Senden ".$errorcode." ".$errormsg, 0);
				IPS_SemaphoreLeave("ClientSocket");
				return;
			}
			//Now receive reply from server
			if(socket_recv ($this->Socket, $Response, $ResponseLen, MSG_WAITALL ) === FALSE) {
				$errorcode = socket_last_error();
				$errormsg = socket_strerror($errorcode);
				IPS_LogMessage("IPS2PanasonicPTLB10VE Socket", "Fehler beim Empfangen ".$errorcode." ".$errormsg);
				$this->SendDebug("CommandClientSocket", "Fehler beim Empfangen ".$errorcode." ".$errormsg, 0);
				$this->SendDebug("CommandClientSocket", "Gesendeter Befehl: ".$Message, 0);
				IPS_SemaphoreLeave("ClientSocket");
				return;
			}
			$this->SendDebug("CommandClientSocket", "Message: ".$Message." Rueckgabe: ".$Response, 0);
			$this->ClientResponse($Message, $Response);
		}	
	return $Result;
	}
	    
	private function ClientResponse($Message, $Response) 
	{
		// Entfernen der Steuerzeichen
		$Response = trim($Response, "\x00..\x1F");
		$Message = trim($Message, "\x00..\x1F");
		
		switch($Message) {
			case 'Q$S':
				If (GetValueInteger($this->GetIDForIdent("Status")) <> $Response) {
					SetValueInteger($this->GetIDForIdent("Status"), $Response);
				}
				If (GetValueBoolean($this->GetIDForIdent("Power")) <> $Response) {
					SetValueBoolean($this->GetIDForIdent("Power"), $Response);
				}
				break;
			case "PON":
				If (GetValueBoolean($this->GetIDForIdent("Power")) == false) {
					SetValueBoolean($this->GetIDForIdent("Power"), true);
				}
				break;
			case "POF":
				If (GetValueBoolean($this->GetIDForIdent("Power")) == true) {
					SetValueBoolean($this->GetIDForIdent("Power"), false);
				}
				break;
			case preg_match('/VOL.*/', $Message) ? $Message : !$Message:
					$Volume = intval(substr($Message, -3));
				   	If (GetValueInteger($this->GetIDForIdent("Volume")) <> ($Volume * 4)) {
						SetValueInteger($this->GetIDForIdent("Volume"), ($Volume * 4));
					}
				break;
			case preg_match('/IIS.*/', $Message) ? $Message : !$Message:
					$Input = substr($Message, -3);
					$InputArray = array("VID", "SVD", "RG1");
					$Key = array_search($Input, $InputArray);
					SetValueInteger($this->GetIDForIdent("Input"), $Key);
				break;
		}
	}
	    

	public function GetStatus()
	{
		If ($this->ReadPropertyBoolean("Open") == true) {
			$this->SendDebug("GetStatus", "Ausfuehrung", 0);
			$this->CommandClientSocket('Q$S', 3);
		}
	}
	    
	private function ConnectionTest()
	{
	      	$result = false;
		$Port = $this->ReadPropertyInteger("Port");
	      	If (Sys_Ping($this->ReadPropertyString("IPAddress"), 2000)) {
			$status = @fsockopen($this->ReadPropertyString("IPAddress"), $Port, $errno, $errstr, 10);
				if (!$status) {
					IPS_LogMessage("IPS2PanasonicPTLB10VE","Port ist geschlossen!");
					$this->SendDebug("ConnectionTest", "Port ist geschlossen!", 0);
	   			}
	   			else {
	   				fclose($status);
					$result = true;
					$this->SetStatus(102);
					$this->SendDebug("ConnectionTest", "Verbindung erfolgreich", 0);
	   			}
		}
		else {
			IPS_LogMessage("IPS2PanasonicPTLB10VE","IP ".$this->ReadPropertyString("IPAddress")." reagiert nicht!");
			$this->SendDebug("ConnectionTest", "IP ".$this->ReadPropertyString("IPAddress")." reagiert nicht!", 0);
			$this->SetStatus(104);
		}
	return $result;
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
