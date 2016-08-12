<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

require_once('Encoding.php');

class KeyUploadService {
    private $ci;
    private $db;
    private $UserID;
    
    private $filename;
    private $loadimages;
    
    private $lpxk;
    
    private $title;
    private $firstStepID;
    private $items;
    private $leads;
    private $itemIDs;
    private $stepIDs;
    
    private $keysid;
    private $newleads;
    
    private $rootNodeID;
    private $nextleadid;
    
    private $leadids;
    private $parentids;
    
    public function __construct() {
        $this->ci =& get_instance();
        $this->ci->load->library('session');
        $this->db = $this->ci->load->database('default', TRUE);
    }
    
    public function loadKey($keyid, $filename, $format, $delimiter=FALSE) {
        set_time_limit(900);
        $this->keysid = $keyid;
        $this->filename = $filename;
        $this->delimiter = $delimiter;
        
        $this->UserID = $this->ci->session->userdata('id');
        
        $this->items = array();
        $this->itemIDs = array();
        $this->leads = array();
        $this->stepIDs = array();
        
        $this->leadids = array();
        $this->parentids = array();
        
        if ($format == 'lpxk') {
            $this->lpxk = new DOMDocument('1.0', 'UTF-8');
            $this->lpxk->load($this->filename);
            $this->parseLpxk();
        }
        elseif ($format == 'delimitedtext') {
            $this->parseDelimitedText();
        }
        
        $this->Items();
        $this->Leads();
        
        $this->updateKey();
        
        return $this->keysid;
    }
    
    private function parseLpxk() {
        // Get the title
        if (!$this->title)
            $this->title = $this->lpxk->getElementsByTagName('PhoenixKey')->item(0)->getAttribute('title');

        // Get end taxa
        $list = $this->lpxk->getElementsByTagName('Identity');
        if ($list->length) {
            foreach ($list as $item) {
                $this->items[] = array(
                    'id' => $item->getAttribute('id'),
                    'name' => $item->getAttribute('name'),
                );
                $this->itemIDs[] = $item->getAttribute('id');
            }
        }

        // Get the ID of the first step in the key
        $list = $this->lpxk->getElementsByTagName('Steps');
        if ($list->length) {
            $this->firstStepID = $list->item(0)->getAttribute('firstStepID');
        }

        // Get all the leads
        $list = $this->lpxk->getElementsByTagName('Lead');
        if ($list->length) {
            foreach ($list as $lead) {
                $text = $lead->getElementsByTagName('Text');
                $text = ($text->length) ? $text->item(0)->nodeValue : NULL;
                $this->leads[] = array(
                    'stepid' => $lead->getAttribute('stepid'),
                    'leadid' => $lead->getAttribute('leadid'),
                    'goto' => $lead->getAttribute('goto'),
                    'leadtext' => $text
                );
                $this->stepIDs[] = $lead->getAttribute('stepid');
            }
        }
    }

    private function parseDelimitedText() {
        $array = array();
        if (!$this->delimiter) {
            $this->delimiter = $this->detectDelimiter($this->filename);
        }
        $handle = fopen($this->filename, 'r');
        while (!feof($handle)) {
            if ($this->delimiter == 'tab') {
                $line = fgetcsv($handle, 0, "\t");
            }
            elseif ($this->delimiter == 'comma') {
                $line = fgetcsv($handle);
            }
            $array[] = array(
                'fromNode' => trim(str_replace(array(':', '.'), '', $line[0])),
                'leadText' => Encoding::toUTF8(trim($line[1])),
                'toNode' => Encoding::toUTF8(trim($line[2])),
            );
        }
        
        // Get the items
        foreach ($array as $row) {
            $goto = $row['toNode'];

            if ($row && !is_numeric($row['toNode'])) {
                $this->items[] = array(
                    'id' => $goto,
                    'name' => $goto,
                );
                $this->itemIDs[] = $goto;
            }
        }
        
        
        // Id of the first step
        $this->firstStepID = $array[0]['fromNode'];
        
        // Get all the leads
        foreach ($array as $index => $row) {
            $goto = $row['toNode'];
            $this->stepIDs[] = $row['fromNode'];
            $this->leads[] = array(
                'stepid' => $row['fromNode'],
                'leadid' => $index + 1,
                'goto' => $goto,
                'leadtext' => $row['leadText'],
            );
        }
    }
    
    private function detectDelimiter($filename) {
        $handle = fopen($filename, 'r');
        $linearray = array();
        while (!feof($handle)) {
            $linearray[] = fgets($handle);
        }
        
        $n = count($linearray);
        $i = 0;
        $numcols = array();
        while ($i < 10 && $i < $n) {
            $row = str_getcsv($linearray[$i], "\t");
            $numcols[] = count($row);
            $i++;
        }
        $sum = array_sum($numcols);
        $count = count($numcols);
        return ($sum/$count > 2) ? 'tab' : 'comma';
    }
    
    private function Items() {
        $this->LinkedItems();
        
        $select = "SELECT max(ItemsID) as max
            FROM items";
        $query = $this->db->query($select);
        $row = $query->row();
        $newitemsid = $row->max + 1;
        
        $select = "SELECT ItemsID
            FROM items
            WHERE Name=?";

        $insert = "INSERT INTO items (ItemsID, Name)
            VALUES (?, ?)";
        
        foreach ($this->items as $key=>$item) {
            $query = $this->db->query($select, array($item['name']));
            if ($query->num_rows()) { // item already in database
                $row = $query->row();
                $this->items[$key]['ItemsID'] = $row->ItemsID;
            }
            else {
                $this->db->query($insert, array($newitemsid, $item['name']));
                $this->items[$key]['ItemsID'] = $newitemsid;
                $newitemsid++;
            }
        }
    }
    
    private function LinkedItems() {
        foreach ($this->items as $index => $item) {
            if (strpos($item['name'], '{')) {
                $itemName = trim(substr($item['name'], 0, strpos($item['name'], '{')));
                $linkedItemName = trim(substr($item['name'], strpos($item['name'], '{') + 1, strpos($item['name'], '}') - strpos($item['name'], '}') -1));
                $this->items[$index]['id'] = $itemName;
                $this->items[$index]['name'] = $itemName;
                $this->itemIDs[$index] = $itemName;
                
                $this->itemIDs[] = $linkedItemName;
                $this->items[] = array(
                    'id' => $linkedItemName,
                    'name' => $linkedItemName,
                );
            }
        }
    }
    
    private function Leads() {
        $this->firstStep();
        
        $select = "SELECT KeysID
            FROM leads
            WHERE KeysID=?";
        
        $delete = "DELETE FROM leads
            WHERE KeysID=?";
        
        $query = $this->db->query($select, array($this->keysid));
        if ($query->num_rows()) {
            $this->db->query($delete, array($this->keysid));
        }
        
        // insert into database
        $fields = array_keys((array) new Lead);
        $values = array();
        foreach ($fields as $field)
            $values[] = '?';
        
        $fields = implode(', ', $fields);
        $values = implode(', ', $values);
        
        $insert = "INSERT INTO leads ($fields)
            VALUES ($values)";
        
        foreach ($this->newleads as $row) {
            $this->db->query($insert, array_values((array) $row));
        } 
    }
    
    private function firstStep() {
        $max = "SELECT MAX(LeadsID) as max
            FROM leads";
        $query = $this->db->query($max);
        $row = $query->row();
        $this->rootNodeID = $row->max + 1;
        $this->nextleadid = $this->rootNodeID;
        
        $this->newleads = array();
        $newlead = new Lead();
        $newlead->KeysID = $this->keysid;
        $newlead->LeadsID = $this->nextleadid;
        $newlead->NodeName = $this->title;
        $newlead->TimestampModified = date('Y-m-d H:i:s');
        $newlead->ModifiedByAgentID = $this->UserID;
        
        $this->newleads[] = $newlead;
        $this->leadids[] = $this->nextleadid;
        $this->parentids[] = NULL;
        
        $this->nextleadid++;
        
        $this->nextStep($newlead->LeadsID, $this->firstStepID);
    }
    
    function nextStep($parentid, $goto) {
        $nextLeadIDs = array_keys($this->stepIDs, $goto);
        foreach ($nextLeadIDs as $key) {
            $thisLead = $this->leads[$key];
            $newlead = new Lead();
            $newlead->KeysID = $this->keysid;
            $newlead->LeadsID = $this->nextleadid;
            $newlead->LeadText = $thisLead['leadtext'];
            $newlead->ParentID = $parentid;
            $newlead->TimestampModified = date('Y-m-d H:i:s');
            $newlead->ModifiedByAgentID = $this->UserID;
            
            $this->newleads[] = $newlead;
            $this->leadids[] = $this->nextleadid;
            $this->parentids[] = $parentid;
            
            $this->nextleadid++;
            
            if (in_array($thisLead['goto'], $this->stepIDs)) {
                $this->nextStep($newlead->LeadsID, $thisLead['goto']);
            }
            else {
                $this->endNode($newlead->LeadsID, $thisLead);
            }
        }
    }
    
    private function endNode($parentID, $lead) {
        $endnode = new Lead();
        $endnode->KeysID = $this->keysid;
        $endnode->LeadsID = $this->nextleadid;
        $endnode->TimestampModified = date('Y-m-d H:i:s');
        $endnode->ModifiedByAgentID = $this->UserID;
        if (strpos($lead['goto'], '{')) {
            $lead['linkto'] = substr($lead['goto'], strpos($lead['goto'], '{') + 1, strpos($lead['goto'], '}') - strpos($lead['goto'], '{') -1);
            $lead['goto'] = trim(substr($lead['goto'], 0, strpos($lead['goto'], '{') - 1));
        }
        $key = array_search($lead['goto'], $this->itemIDs);
        if ($key !== FALSE) {
            $endnode->NodeName = $this->items[$key]['name'];
            $endnode->ItemsID = $this->items[$key]['ItemsID'];
        }
        $endnode->ParentID = $parentID;
        $this->newleads[] = $endnode;
        $this->leadids[] = $this->nextleadid;
        $this->parentids[] = $parentID;
        $this->nextleadid++;
        if (isset($lead['linkto'])) {
            $this->linkTo($endnode, $lead['linkto']);
        }
    }

    private function linkTo($endnode, $linkto) {
        $linkToNode = new Lead();
        $linkToNode->KeysID = $this->keysid;
        $linkToNode->LeadsID = $this->nextleadid;
        $linkToNode->TimestampModified = date('Y-m-d H:i:s');
        $linkToNode->ModifiedByAgentID = $this->UserID;
        $linkToNode->LeadText = '[link through]';

        $key = array_search($linkto, $this->itemIDs);
        if ($key !== FALSE) {
            $linkToNode->NodeName = $this->items[$key]['name'];
            $linkToNode->ItemsID = $this->items[$key]['ItemsID'];
        }
        $linkToNode->ParentID = $endnode->LeadsID;
        $this->newleads[] = $linkToNode;
        $this->leadids[] = $this->nextleadid;
        $this->parentids[] = $endnode->LeadsID;
        $this->nextleadid++;
    }
    
    private function updateKey() {
        $update = "UPDATE `keys`
            SET FirstStepID=$this->rootNodeID, TimestampModified=NOW(), ModifiedByID=$this->UserID, Version=Version+1
            WHERE KeysID=$this->keysid";
        $query = $this->db->query($update);
    }
    
}

class Lead {
    var $LeadsID = NULL;
    var $KeysID = NULL;
    var $NodeName = NULL;
    var $LeadText = NULL;
    var $ParentID = NULL;
    var $ItemsID = NULL;
    var $MediaID = NULL;
    var $ItemUrl = NULL;
    var $TimestampCreated = NULL;
    var $TimestampModified = NULL;
    var $ModifiedByAgentID = NULL;
}


/* End of file KeyUploadService.php */
/* Location: ./libraries/KeyUploadService.php */