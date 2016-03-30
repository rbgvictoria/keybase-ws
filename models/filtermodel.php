<?php
require_once 'webservicemodel.php';

class FilterModel extends WebServiceModel {
    private $items;
    private $found;
    private $notfound;
    
    private $filterKeys;
    private $filterKeyIDs;
    private $filterItems;

    public function __construct() {
        parent::__construct();
    }
    
    public function getFilters($project=FALSE, $user=FALSE, $session=FALSE) {
        $ret = array();
        $this->db->select('f.GlobalFilterID, f.FilterID, f.Name');
        $this->db->from('globalfilter f');
        $this->db->order_by('f.TimestampCreated');
        
        if ($user) {
            $this->db->where('f.UsersID', $user);
        }
        elseif ($session) {
            $this->db->where('f.SessionID', $session);
        }
        if ($project) {
            $this->db->join('filterproject fp', 'f.GlobalFilterID=fp.FilterID');
            $this->db->where('fp.ProjectID', $project);
        }
        
        $query = $this->db->get();
        if ($query->num_rows()) {
            foreach ($query->result() as $row)
                $ret[$row->FilterID] = ($row->Name) ? $row->Name : $row->FilterID;
        }
        return $ret;
    }
    
    public function getProjectFilters($projectid=FALSE, $userid=FALSE) {
        $this->db->select('p.ProjectsID AS project_id, p.Name AS project_name, f.FilterID AS filter_id, f.Name AS filter_name');
        $this->db->from('projects p');
        $this->db->join('filterproject fp', 'p.ProjectsID=fp.ProjectID');
        $this->db->join('globalfilter f', 'fp.FilterID=f.GlobalFilterID AND f.IsProjectFilter=true', FALSE, FALSE);
        $this->db->order_by('project_name');
        $this->db->order_by('filter_name');
        
        if ($projectid) {
            $this->db->where('p.ProjectsID', $projectid);
        }
        if ($userid) {
            $this->db->join('projects_users pu', 'p.ProjectsID=pu.ProjectsID');
            $this->db->join('users u', 'pu.UsersID=u.UsersID');
            $this->db->where('u.UsersID', $userid);
            $this->db->where('pu.Role', 'Manager');
        }
        
        $query = $this->db->get();
        return $query->result_array();
    }
    
    public function manageFilters($project) {
        $this->db->select('f.GlobalFilterID AS global_filter_id, f.FilterID AS filter_id, 
            f.Name AS filter_name, u.Username AS user_name, pu.Role AS user_role, 
            f.IsProjectFilter=1 AS is_project_filter', FALSE);
        $this->db->from('globalfilter f');
        $this->db->join('filterproject fp', 'f.GlobalFilterID=fp.FilterID');
        $this->db->join('projects_users pu', 'fp.ProjectID=pu.ProjectsID AND f.UsersID=pu.UsersID');
        $this->db->join('users u', 'pu.UsersID=u.UsersID');
        $this->db->where('fp.ProjectID', $project);
        $this->db->group_by('f.GlobalFilterID');
        $this->db->having('count(f.GlobalFilterID)=1');
        $query = $this->db->get();
        return $query->result();
    }
    
    public function getFilterItems ($taxa, $projects=FALSE) {
        $this->found = array();
        $this->notfound = array();
        $this->db->select('i.ItemsID, i.Name');
        $this->db->from('keys k');
        $this->db->join('leads l', 'k.KeysID=l.KeysID');
        $this->db->join('groupitem g0', 'l.ItemsID=g0.GroupID AND g0.OrderNumber=0', 'left', FALSE);
        $this->db->join('groupitem g1', 'l.ItemsID=g1.GroupID AND g1.OrderNumber=1', 'left', FALSE);
        $this->db->join('items i', 'COALESCE(g1.MemberID, g0.MemberID, l.ItemsID)=i.ItemsID', 'inner', FALSE);
        $this->db->where_in('i.Name', $taxa);
        if ($projects) {
            $this->db->where_in('k.ProjectsID', $projects);
        }
        $query = $this->db->get();
        if ($query->num_rows()) {
            foreach ($query->result() as $row) {
                $this->found[] = $row->Name;
                $this->items[] = $row->ItemsID;
            }
        }
        $this->notfound = array_diff($taxa, $this->found);
        
        if ($this->found) {
            sort($this->found);
        }
        if ($this->notfound) {
            sort($this->notfound);
        }
        return $this->items;
    }

    public function updateFilter($filterid=FALSE, $filtername=FALSE, $projects=FALSE, $session=FALSE) {
        if ($filterid) {
            $this->db->select('GlobalFilterID');
            $this->db->where('FilterID', $filterid);
        }
        else {
            $this->db->select('MAX(GlobalFilterID)+1 AS GlobalFilterID', FALSE);
        }
        $this->db->from('globalfilter');
        $query = $this->db->get();
        $row = $query->row();
        $id = $row->GlobalFilterID;
        
        $filterArray = array(
            'Name' => ($filtername) ? $filtername : NULL,
            'FilterItems' => serialize($this->items),
            'FilterProjects' => ($projects) ? serialize($projects) : NULL
        );
        if ($this->notfound) {
            $filterArray['ItemsNotFound'] = implode('|', $this->notfound);
        }
        
        if ($filterid) {
            $this->db->where('FilterID', $filterid);
            $updateArray = array_merge($filterArray, array(
                'TimestampModified' => date('Y-m-d H:i:s')
            ));
            $this->db->update('globalfilter', $updateArray);
        }
        else {
            $filterid = uniqid();
            $insertArray = array_merge($filterArray, array(
                'GlobalFilterID' => $id,
                'FilterID' => $filterid,
                'TimestampCreated' => date('Y-m-d H:i:s'),
                'UsersID' => $this->input->post('keybase_user_id') ? $this->input->post('keybase_user_id') : NULL,
                'IPAddress' => $this->input->ip_address(),
                'SessionID' => $session,
                'FilterProjects' => ($projects) ? serialize($projects) : NULL
            ));
            $this->db->insert('globalfilter', $insertArray);
        }
        
        $this->db->where('FilterID', $id);
        $this->db->delete('filterproject');
        if ($projects) {
            foreach ($projects as $project) {
                $this->db->insert('filterproject', array(
                    'FilterID' => $id,
                    'ProjectID' => $project
                ));
            }
        }
        return $filterid;
    }
    
    public function deleteFilter($filterid, $userid) {
        $this->db->select('GlobalFilterID');
        $this->db->from('globalfilter');
        $this->db->where('FilterID', $filterid);
        $this->db->where('UsersID', $userid);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            $this->db->where('GlobalFilterID', $row->GlobalFilterID);
            if ($this->db->delete('globalfilter')) {
                return TRUE;
            }
        }
        return FALSE;
    }
    
    public function setProjectFilter($filter, $isProjectFilter) {
        $isProjectFilter = ($isProjectFilter) ? 1 : 0;
        $update = "UPDATE globalfilter SET IsProjectFilter=$isProjectFilter WHERE FilterID='$filter'";
        $this->db->query($update);
        return $update;
    }
    
    public function getGlobalFilterTaxa($filterid) {
        $this->db->select('FilterItems');
        $this->db->from('globalfilter');
        $this->db->where('FilterID', $filterid);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            $ret = array();
            $items = unserialize($row->FilterItems);
            foreach ($items as $item)
                $ret[] = $this->getTaxonName ($item);
            sort($ret);
            return $ret;
        }
    }
    
    public function getGlobalfilterProjects($filterid) {
        $this->db->select('FilterProjects');
        $this->db->from('globalfilter');
        $this->db->where('FilterID', $filterid);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            $projects = unserialize($row->FilterProjects);
            return $projects;
        }
        else
            return FALSE;
    }
    
    public function getGlobalfilterMetadata($filterid) {
        $this->db->select('Name AS FilterName, FilterID');
        $this->db->from('globalfilter');
        $this->db->where('FilterID', $filterid);
        $query = $this->db->get();
        if ($query->num_rows())
            return $query->row();
        else
            return FALSE;
    }
    
    public function globalFilter($filter) {
        $this->filterKeys = array();
        $this->filterKeyIDs = array();
        $this->db->select('FilterItems, FilterProjects, FilterID, Name, TimestampCreated');
        $this->db->from('globalfilter');
        $this->db->where('FilterID', $filter);
        $query = $this->db->get();
        if ($query->num_rows()) {
            $row = $query->row();
            $filterItems = unserialize($row->FilterItems);
            $projects = NULL;
            $projects = unserialize($row->FilterProjects);
            $this->globalFilterProjects($projects);
            $this->getGlobalFilterKeys($filterItems, $projects);
            $this->getGlobalFilterItems();
            return (object) array(
                'filterID' => $row->FilterID,
                'filterName' => $row->Name,
                'created' => $row->TimestampCreated,
                'numItems' => count($this->filterItems),
                'numItemsOrig' => count(unserialize($row->FilterItems)),
                'numKeys' => count($this->filterKeys),
                'projects' => $this->filterProjects,
                'items' => $this->filterItems,
                'keys' => $this->filterKeys
            );
        }
        else {
            return FALSE;
        }
    }
    
    private function globalFilterProjects($projects) {
        $this->db->select('ProjectsID AS projectID, Name AS projectName, taxonomicScopeID');
        $this->db->from('projects');
        $this->db->where_in('ProjectsID', $projects);
        $query = $this->db->get();
        $this->filterProjects = $query->result();
    }
    
    
    
    private function getGlobalFilterKeys($items, $projects=FALSE) {
        $newItems = array();
        $this->db->select('k.ProjectsID, k.KeysID, k.TaxonomicScopeID, k.Name AS KeyName, 
            group_concat(DISTINCT cast(l.ItemsID as char)) AS Items', FALSE);
        $this->db->from('keys k');
        $this->db->join('leads l', 'k.KeysID=l.KeysID');
        $this->db->join('groupitem g0', 'l.ItemsID=g0.GroupID AND g0.OrderNumber=0', 'left', FALSE);
        $this->db->join('groupitem g1', 'l.ItemsID=g1.GroupID AND g1.OrderNumber=1', 'left', FALSE);
        $this->db->join('items i', 'coalesce(g1.MemberID, g0.MemberID, l.ItemsID)=i.ItemsID', 'inner', FALSE);
        if ($projects) {
            $this->db->where_in('k.ProjectsID', $projects);
        }
        $this->db->where_in('i.itemsID', $items);
        $this->db->group_by('k.KeysID');
        $query = $this->db->get();
        if ($query->num_rows()) {
            foreach ($query->result() as $row) {
                $key = array();
                $key['keyID'] = $row->KeysID;
                $key['projectID'] = $row->ProjectsID;
                $key['keyName'] = $row->KeyName;
                $key['taxonomicScopeID'] = $row->TaxonomicScopeID;
                $key['items'] = explode(',', $row->Items);
                
                if (in_array($key['keyID'], $this->filterKeyIDs)) {
                    $k = array_search($key['keyID'], $this->filterKeyIDs);
                    $this->filterKeys[$k]->items = array_unique(array_merge($this->filterKeys[$k]->items, $key['items']));
                }
                else {
                    $this->filterKeyIDs[] = $key['keyID'];
                    $this->filterKeys[] = (object) $key;
                    $newItems[] = $row->TaxonomicScopeID;
                }
            }
            if ($newItems) {
                $this->getGlobalFilterKeys($newItems, $projects);
            }
        }
    }

    private function getGlobalFilterItems() {
        $itemIDs = array();
        foreach ($this->filterKeys as $key) {
            $itemIDs = array_merge($itemIDs, $key->items);
        }
        $itemIDs = array_unique($itemIDs);
        
        $this->db->select('ItemsID AS itemID, Name AS itemName');
        $this->db->from('items');
        $this->db->where_in('ItemsID', $itemIDs);
        $query = $this->db->get();
        $this->filterItems = $query->result();
    }
}

/* End of file filtermodel.php */
/* Location: ./models/filtermodel.php */
