<?php

require_once 'webservicemodel.php';

class KeyModel extends WebServiceModel {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function getKey($keyid) {
        $this->db->select("k.KeysID AS key_id, 
            k.Name AS key_name, 
            k.UID, 
            k.Description AS description, 
            k.Rank AS rank, 
            k.TaxonomicScope AS taxonomic_scope, 
            k.GeographicScope AS geographic_scope, 
            k.Notes AS notes,
            k.CreatedByID AS created_by_id,
            k.TimestampCreated as timestamp_created,
            k.ModifiedByID as modified_by_id,
            k.TimestampModified as timestamp_modified", FALSE);
        $this->db->from('keys k');
        $this->db->where('k.KeysID', $keyid);
        $query = $this->db->get();
        
        if ($query->num_rows()) {
            return $query->row();
        }
    }

    function getSource($keyid) {
        $this->db->select("s.Authors AS author, s.Year AS publication_year, s.Title AS title, s.InAuthors AS in_author, 
            s.InTitle AS in_title, s.Edition AS edition, s.Journal AS journal, s.Series AS series, s.Volume AS volume, 
            s.Part AS part, s.Publisher AS publisher, s.PlaceOfPublication AS place_of_publication, s.Pages AS page, 
            s.Modified AS is_modified, s.Url AS url");
        $this->db->from('keys k');
        $this->db->join('sources s', 'k.SourcesID=s.SourcesID', 'left');
        $this->db->where('k.KeysID', $keyid);
        $query = $this->db->get();
        
        if ($query->num_rows()) {
            return $query->row_array();
        }
    }

    public function getKeyItems($keysid) {
        $bie = 'http://bie.ala.org.au/species/';
        
        $query = $this->db->query("SELECT count(*) AS num FROM projectitems WHERE ProjectsID=(SELECT ProjectsID FROM `keys` WHERE KeysID=$keysid)", FALSE);
        $row = $query->row();
        $hasProjectItems = ($row->num) ? TRUE : FALSE;
        
        if ($hasProjectItems) {
            $this->db->select('i.ItemsID AS item_id, 
                coalesce(m.Name, i.Name) AS item_name,
                coalesce(mpi.Url, pi.Url) AS url,
                coalesce(mkto.KeysID, kto.KeysID) AS to_key,
                mlt.ItemsID AS link_to_item_id,
                mlt.Name AS link_to_item_name,
                lpi.Url AS link_to_url,
                mltkto.KeysID AS link_to_key', FALSE);
        }
        else {
            $this->db->select("i.ItemsID AS item_id, 
                coalesce(m.Name, i.Name) AS item_name,
                concat('$bie', coalesce(m.LSID, i.LSID)) AS url,
                coalesce(mkto.KeysID, kto.KeysID) AS to_key,
                mlt.ItemsID AS link_to_item_id,
                mlt.Name AS link_to_item_name,
                concat('$bie', mlt.LSID) AS link_to_url,
                mltkto.KeysID AS link_to_key", FALSE);
        }
        $this->db->from('leads l');
        $this->db->join('keys k', 'l.keysID=k.KeysID');
        $this->db->join('items i', 'l.ItemsID=i.ItemsID');
        $this->db->join('keys kto', 'l.ItemsID=kto.TaxonomicScopeID AND k.ProjectsID=kto.ProjectsID', 'left');
        $this->db->join('groupitem gi', 'l.ItemsID=gi.GroupID AND gi.OrderNumber=0', 'left', FALSE);
        $this->db->join('items m', 'gi.MemberID=m.ItemsID', 'left');
        $this->db->join('keys mkto', 'm.ItemsID=mkto.TaxonomicScopeID AND k.ProjectsID=mkto.ProjectsID', 'left');
        $this->db->join('groupitem gilt', 'l.ItemsID=gilt.GroupID AND gilt.OrderNumber=1', 'left', FALSE);
        $this->db->join('items mlt', 'gilt.MemberID=mlt.ItemsID', 'left');
        $this->db->join('keys mltkto', 'mlt.ItemsID=mltkto.TaxonomicScopeID AND k.ProjectsID=mltkto.ProjectsID', 'left');
        
        if ($hasProjectItems) {
            $this->db->join('projectitems pi', "i.ItemsID=pi.ItemsID AND pi.ProjectsID=k.ProjectsID", 'left', FALSE);
            $this->db->join('projectitems mpi', "m.ItemsID=mpi.ItemsID AND mpi.ProjectsID=k.ProjectsID", 'left', FALSE);
            $this->db->join('projectitems lpi', "mlt.ItemsID=lpi.ItemsID AND lpi.ProjectsID=k.ProjectsID", 'left', FALSE);
        }
        
        $this->db->where('l.KeysID', $keysid);
        $this->db->group_by('item_id');
        $this->db->order_by('item_name, link_to_item_name');
        
        $query = $this->db->get();
        if ($query->num_rows())
            return $query->result_array();
        else
            return FALSE;
    }
    
    public function getRootNode($keysID) {
        $this->db->select('LeadsID AS root_node_id /*, NodeNumber AS `left`, HighestDescendantNodeNumber AS `right`*/', FALSE);
        $this->db->from('leads');
        $this->db->where('KeysID', $keysID);
        $this->db->where('NodeNumber', 1);
        $query = $this->db->get();
        return $query->row();
    }

    public function getLeads($keysid) {
        $this->db->select("p.ParentID AS parent_id, p.LeadsID AS lead_id, /*p.NodeNumber AS `left`, p.HighestDescendantNodeNumber AS `right`,*/ 
            p.LeadText AS lead_text, l.ItemsID AS item", false);
        $this->db->from('leads p');
        $this->db->join('leads l', 'p.LeadsID=l.ParentID AND l.NodeName IS NOT NULL', 'left', false);
        $this->db->where('p.KeysID', $keysid);
        $this->db->where('p.LeadText IS NOT NULL', false, false);
        $this->db->order_by('p.ParentID, p.NodeNumber');
        $query = $this->db->get();
        return $query->result();
    }

    public function getCitation($keyid) {
        $this->db->select('s.Authors, s.`Year`, s.Title, s.InAuthors, s.InTitle, s.Journal, s.Series, s.Volume, s.Part, 
            s.Publisher, s.PlaceOfPublication, s.Pages, s.Modified');
        $this->db->from('sources s');
        $this->db->join('keys k', 's.SourcesID=k.SourcesID');
        $this->db->where('k.KeysID', $keyid);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            $ret = FALSE;
            if ($row->Authors && $row->Year && $row->Title) {
                if ($row->Modified)
                    $ret .= 'Modified from: ';
                else
                    $ret .= 'From: ';
                $ret .= '<b>' . $row->Authors . '</b> (' . $row->Year . '). ';
                if ($row->Journal) {
                    $ret .= $row->Title . '. <i>' . $row->Journal . '</i>';
                    if ($row->Series)
                        $ret .= ', ser. ' . $row->Series;
                    $ret .= ' <b>' . $row->Volume . '</b>';
                    if ($row->Part) 
                        $ret .= '(' . $row->Part . ')';
                    $ret .= ':' . $row->Pages . '.';
                }
                elseif ($row->InTitle) {
                    $ret .= $row->Title . '. In: ';
                    if ($row->InAuthors) 
                        $ret .= $row->InAuthors . ', ';
                    $ret .= '<i>' . $row->InTitle . '</i>';
                    if ($row->Volume) 
                        $ret .= ' <b>' . $row->Volume . '</b>';
                    if ($row->Pages)
                        $ret .= ', pp. ' . $row->Pages;
                    $ret .= '.';
                    if ($row->Publisher) {
                        $ret .= ' ' . $row->Publisher;
                        if ($row->PlaceOfPublication)
                            $ret .= ', ';
                        else
                            $ret .= '.';
                    }
                    if ($row->PlaceOfPublication)
                        $ret .= ' ' . $row->PlaceOfPublication . '.';
                }
                else {
                    $ret .= '<i>' . $row->Title . '</i>.';
                    if ($row->Publisher) {
                        $ret .= ' ' . $row->Publisher;
                        if ($row->PlaceOfPublication)
                            $ret .= ', ';
                        else
                            $ret .= '.';
                    }
                    if ($row->PlaceOfPublication)
                        $ret .= ' ' . $row->PlaceOfPublication . '.';
                    
                }
            }
            return $ret;
        }
        else
            return FALSE;
    }

    function getBreadCrumbs($key) {
        $this->BreadCrumbs = array();
        $this->getCrumb($key);
        return array_reverse($this->BreadCrumbs);
    }
    
    function getCrumb($key) {
        $this->db->select('coalesce(pk.KeysID, gpk.KeysID, gltpk.KeysID) AS key_id, coalesce(pk.Name, gpk.Name, gltpk.Name) AS key_name', FALSE);
        $this->db->from('keys k');
        $this->db->join('leads l', 'k.TaxonomicScopeID=l.ItemsID', 'left');
        $this->db->join('keys pk', 'l.KeysID=pk.KeysID AND k.ProjectsID=pk.ProjectsID', 'left');
        $this->db->join('groupitem g', 'k.TaxonomicScopeID=g.MemberID AND g.OrderNumber=0', 'left', FALSE);
        $this->db->join('leads gl', 'g.GroupID=gl.ItemsID', 'left');
        $this->db->join('keys gpk', 'gl.KeysID=gpk.KeysID AND k.ProjectsID=gpk.ProjectsID', 'left');
        $this->db->join('groupitem glt', 'k.TaxonomicScopeID=glt.MemberID AND glt.OrderNumber=1', 'left', FALSE);
        $this->db->join('leads gltl', 'glt.GroupID=gltl.ItemsID', 'left');
        $this->db->join('keys gltpk', 'gltl.KeysID=gltpk.KeysID AND k.ProjectsID=gltpk.ProjectsID', 'left');
        $this->db->where('k.KeysID', $key);
        $this->db->where('coalesce(pk.KeysID, gpk.KeysID, gltpk.KeysID) IS NOT NULL', FALSE, FALSE);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row_array();
            $this->BreadCrumbs[] = $row;
            $this->getCrumb($row['key_id']);
        }
    }

    public function getChanges($keyid) {
        $this->db->select("CONCAT_WS(' ', u.FirstName, u.LastName) AS full_name,
            TimestampModified as timestamp_modified, c.`comment`", FALSE);
        $this->db->from('changes c');
        $this->db->join('users u', 'c.ModifiedByAgentID=u.UsersID');
        $this->db->where('c.KeysID', $keyid);
        $this->db->order_by('TimestampModified', 'desc');
        $query = $this->db->get();
        if ($query->num_rows())
            return $query->result_array();
        else
            return FALSE;
    }
    
    public function editKeyMetadata($data, $userid=FALSE) {
        if (is_object($data)) {
            $data = (array) $data;
        }
        $updateArray = array(
            'Name' => $data['key_name'],
            'Description' => $data['description'],
            'TaxonomicScope' => $data['taxonomic_scope'],
            'GeographicScope' => $data['geographic_scope'],
            'Notes' => (isset($data['notes'])) ? $data['notes'] : FALSE,
            'ProjectsID' => $data['project_id'],
            'CreatedByID' => $data['created_by_id'],
            'ModifiedByID' => ($userid) ? $userid : NULL
        );
        
        if (!(isset($data['key_id']) && $data['key_id'])) {
            $insertArray = $updateArray;
            $this->db->select('MAX(KeysID) AS max, MAX(UID) AS maxuid', FALSE);
            $this->db->from('keys');
            $query = $this->db->get();
            $row = $query->row();
            $keyid = ($row->max) ? $row->max + 1 : 1;
            $insertArray['KeysID'] = $keyid;
            $insertArray['UID'] = ($row->maxuid) ? str_pad($row->maxuid + 1, 6, '0', STR_PAD_LEFT) : '000001';
            $insertArray['Name'] = $data['key_name'];
            $insertArray['TimestampCreated'] = date('Y-m-d H:i:s');
            $insertArray['CreatedByID'] = ($userid) ? $userid : NULL;
            $this->db->insert('keys', $insertArray);
            $insertArray = array();
        } 
        else {
            $keyid = $data['key_id'];
        }
        
        if ($data['taxonomic_scope']) {
            $this->db->select('ItemsID');
            $this->db->from('items');
            $this->db->where('Name', $data['taxonomic_scope']);
            $query = $this->db->get();
            if ($query->num_rows()) {
                $row = $query->row();
                $updateArray['TaxonomicScopeID'] = $row->ItemsID;
            }
            else {
                $insertArray = array();
                $this->db->select('MAX(ItemsID) AS max', FALSE);
                $this->db->from('items');
                $query = $this->db->get();
                $row = $query->row();
                $itemsid = ($row->max) ? $row->max + 1 : 1;
                $updateArray['TaxonomicScopeID'] = $itemsid;
                $insertArray['ItemsID'] = $itemsid;
                $insertArray['Name'] = $data['taxonomic_scope'];
                $this->db->insert('items', $insertArray);
            }
        }

        if ($data['source'] && ($data['source']->author || $data['source']->title)) {
            $updateArray['SourcesID'] = $this->updateSource($keyid, $data['source']);
        }
        elseif (isset($data['source_id'])) {
            $updateArray['SourcesID'] = $data['source_id'];
        }
        $timestamp = date('Y-m-d H:i:s');
        $updateArray['TimestampModified'] = $timestamp;
        
        $this->db->where('KeysID', $keyid);
        $this->db->update('keys', $updateArray);
        
        if (isset($data['key_id']) && (!empty($data['change_comment']))) {
            $changesArray = array(
                'KeysID' => $data['key_id'],
                'TimestampModified' => $timestamp,
            );
            if (isset($data['change_comment'])) 
                $changesArray['Comment'] = $data['change_comment'];
            if ($userid)
                $changesArray['ModifiedByAgentID'] = $userid;
            $this->db->insert('changes', $changesArray);
        }
        return $keyid;
        
    }
    
    private function updateSource($keyid, $source) {
        if (is_object($source)) {
            $source = (array) $source;
        }
        $updArray = array(
            'Authors' => $source['author'],
            'Year' => $source['publication_year'],
            'Title' => $source['title'],
            'InAuthors' => $source['in_author'],
            'InTitle' => $source['in_title'],
            'Edition' => $source['edition'],
            'Journal' => $source['journal'],
            'Volume' => $source['volume'],
            'Part' => $source['part'],
            'Pages' => $source['page'],
            'Publisher' => $source['publisher'],
            'PlaceOfPublication' => $source['place_of_publication'],
            'Url' => $source['url'],
        );
        if (isset($source['is_modified']))
            $updArray['Modified'] = $source['is_modified'];
        else
            $updArray['Modified'] = NULL;

        $this->db->select('SourcesID');
        $this->db->from('keys');
        $this->db->where('KeysID', $keyid);
        $query = $this->db->get();
        $row = $query->row();
        if ($row->SourcesID) {
            $sourceid = $row->SourcesID;
            $this->db->where('SourcesID', $row->SourcesID);
            $this->db->update('sources', $updArray);
        }
        else {
            $this->db->select('MAX(SourcesID) AS max', FALSE);
            $this->db->from('sources');
            $query = $this->db->get();
            $row = $query->row();
            $sourceid  = ($row->max) ? $row->max + 1 : 1;
            $updArray['SourcesID'] = $sourceid;
            $this->db->insert('sources', $updArray);
        }
        
        return $sourceid;
    }
    
    public function deleteKey($keyid, $userid) {
        $check = $this->checkPriviliges($keyid, $userid);
        if ($check) {
            $this->db->trans_start();
            
            $this->db->where('KeysID', $keyid);
            $this->db->delete('leads');
            
            $this->db->where('KeysID', $keyid);
            $this->db->delete('keys');
            
            $this->db->trans_complete();
        }
        else {
            return FALSE;
        }
    }
    
    protected function checkPriviliges($keyid, $userid) {
        $this->db->select('k.KeysID');
        $this->db->from('keys k');
        $this->db->join('projects p', 'k.ProjectsID=p.ProjectsID', 'left');
        $this->db->join('projects_users pu', 'p.ProjectsID=pu.ProjectsID', 'left');
        $this->db->where("(k.CreatedByID=$userid OR (pu.UsersID=$userid AND pu.Role='Manager'))", FALSE, FALSE);
        $this->db->where('k.KeysID', $keyid);
        $query = $this->db->get();
        if ($query->num_rows()) {
            return TRUE;
        }
        else {
            return FALSE;
        }
    }
    
}