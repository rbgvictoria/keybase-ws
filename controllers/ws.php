<?php

class WS extends CI_Controller {
    
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper('json');
        $this->load->model('keymodel');
        $this->load->model('projectmodel');
        $this->load->model('filtermodel');
        $this->load->model('sourcemodel');
    }
    
    public function key($id=false) {
        $this->key_get($id);
    }
    
    public function key_get($id=FALSE) {
        if (!$id) {
            $id = $this->input->get('key_id');
        }
        if (!$id) {
            exit();
        }
        $data = $this->keymodel->getKey($id);
        $data->project = (object) $this->keymodel->getProjectDetails($id);
        $data->project->project_icon = 'https://keybase.rbg.vic.gov.au/images/projecticons/' . $data->project->project_icon;
        $data->breadcrumbs = $this->keymodel->getBreadCrumbs($id);
        $data->taxonomic_scope = $this->keymodel->getItem($data->TaxonomicScopeID, $data->project->project_id);
        $data->key_name = $data->taxonomic_scope->item_name;
        unset($data->TaxonomicScopeID);
        $data->source = (object) $this->keymodel->getSource($id);
        $data->modified_from_source = ($data->modified_from_source) ? true : false;
        $data->source->citation = $this->keymodel->getCitation($id);
        $data->items = $this->keymodel->getKeyItems($id);
        $data->first_step = $this->keymodel->getRootNode($id);
        $data->leads = $this->keymodel->getLeads($id);
        echo json_output($data);
    }
    
    public function key_post($id=FALSE) {
        $this->session->set_userdata('id', $this->input->post('keybase_user_id'));
        $keyid = $this->key_meta_post($id);
        if (isset($_FILES['file_content']['tmp_name'])) {
            $this->load->library('KeyUploadService');
            if ($_FILES['file_content']['type'] == 'text/csv') {
                $this->keyuploadservice->loadKey($keyid, $_FILES['file_content']['tmp_name'], 'delimitedtext');
            }
        }
        elseif ($this->input->post('leads')) {
            $this->load->library('KeyUploadService');
            $this->keyuploadservice->loadKey($keyid, $this->input->post('leads'), 'json');
        }
        echo json_output($keyid);
    }
    
    public function key_delete($id) {
        $result = $this->keymodel->deleteKey($id, $this->input->post('keybase_user_id'));
        echo json_output($result);
    }
    
    public function key_meta($id) {
        $this->key_meta_get($id);
    }
    
    public function key_meta_get($id) {
        $data = $this->keymodel->getKey($id);
        $data->created_by = $this->keymodel->getUser($data->created_by_id);
        unset($data->created_by_id);
        $data->modified_by = $this->keymodel->getUser($data->modified_by_id);
        unset($data->modified_by_id);
        if ($data->source_id) {
            //$data->source = (object) $this->sourcemodel->getSource($data->source_id);
            $data->modified_from_source = ($data->modified_from_source) ? true : false;
        }
        else {
            unset($data->modified_from_source);
        }
//        $data->source->citation = $this->keymodel->getCitation($id);
        $data->project = (object) $this->keymodel->getProjectDetails($id);
        $data->project->project_icon = $data->project->project_icon ? 'https://keybase.rbg.vic.gov.au/images/projecticons/' . $data->project->project_icon : NULL;
        $data->breadcrumbs = $this->keymodel->getBreadCrumbs($id);
        
        $changes = $this->keymodel->getChanges($id);
        $data->changes = ($changes) ? $changes : null;
        echo json_output($data);
    }
    
    public function key_meta_post($id) {
        $this->session->unset_userdata('id');
        $this->session->set_userdata('id', $this->input->post('keybase_user_id'));
        $keyMetadata = json_decode($this->input->post('key_metadata'));
        $result = $this->keymodel->editKeyMetadata($keyMetadata, $this->session->userdata('id'));
        return $result;
    }
    
    public function source_get($id) {
        $data = $this->sourcemodel->getSource($id);
        echo json_output($data);
    }
    
    public function source_post() {
        $input = $_POST;
        if (!$input) {
            parse_str($_SERVER['QUERY_STRING'], $input);
        }
        print_r($input);
        $data = $this->sourcemodel->editSource($input);
        echo json_output($data);
    }
    
    public function source_autocomplete() {
        if (empty($_GET['term'])) exit;
        $uri = (object) $this->uri->uri_to_assoc(3, array('project'));
        $term = $this->input->get('term');
        $data = $this->sourcemodel->autocomplete($term, $uri->project);
        echo json_output($data);
    }
    
    public function project_users($project) {
        $this->project_user_get($project);
    }
    
    public function project_user_get($project) {
        $data = $this->keymodel->getProjectUsers($project);
        echo json_output($data);
    }
    
    public function project_user_put() {
        $data = $this->projectmodel->addProjectUser($this->input->post());
        echo json_output($data);
    }
    
    public function project_user_delete($id) {
        $response = $this->projectmodel->deleteProjectUser($id, $this->input->post());
        echo json_output($response);
    }
    
    public function search_items($searchstring=false) {
        $term = ($searchstring) ?: $this->input->get('term');
        if (!$term) {
            exit('A search term is required');
        }
        $data = $this->keymodel->getSearchResult(urldecode($term));
        if ($data) {
            foreach ($data as $index => $row) {
                $row->project = $this->keymodel->getProjectDetails($row->key_id);
                $data[$index] = $row;
            }
        }
        echo json_output($data);
    }
    
    public function autocomplete_item_name() {
        if (!$this->input->get('term')) exit;
        $q = strtolower($this->input->get('term'));
        $items = $this->keymodel->autoCompleteItemName($q);
        echo json_output($items);
    }

    public function export($format, $keyid) {
        $this->output->enable_profiler(FALSE);
        $this->load->model('exportmodel');
        $key = $this->exportmodel->export($keyid);
        
        $this->load->library('ExportService');
        
        if ($format == 'lpxk') {
            $lpxk = $this->exportservice->exportToLpxk($key);
            header('Content-type: text/xml');
            echo $lpxk;
        }
        elseif ($format == 'csv') {
            $filename = 'keybase_export_' . $keyid . '_' . time() . '.csv';
            $csv = $this->exportservice->exportToCsv($key);
            header('Content-type: text/csv');
            header('Content-disposition: attachment;filename=' . $filename);
            echo $csv;
        }
        elseif ($format == 'txt') {
            $filename = 'keybase_export_' . $keyid . '_' . time() . '.txt';
            $csv = $this->exportservice->exportToCsv($key, 'tab');
            header('Content-type: text/plain');
            header('Content-disposition: attachment;filename=' . $filename);
            echo $csv;
        }
        elseif ($format == 'sdd') {
            $sdd = $this->exportservice->exportToSdd($key);
            header('Content-type: text/xml');
            echo $sdd;
        }
    }
    
    public function projects_get() {
        $data = $this->projectmodel->getProjects();
        echo json_output($data);
    }
    
    
    public function total_items_get() {
        $data = $this->projectmodel->getNumberOfItems();
        echo json_output($data);
    }
    
    public function project_get($project) {
        $data = $this->_project_meta_get($project);
        $data['first_key'] = $this->projectmodel->getFirstKey($project);
        $data['keys'] = $this->_project_keys_get($project);
        echo json_output($data);
    }
    
    public function project_meta_get($project) {
        $data = $this->_project_meta_get($project);
        echo json_output($data);
    }
    
    public function project_keys_get($project) {
        $data = $this->_project_keys_get($project);
        echo json_output($data);
    } 
    
    private function _project_meta_get($project) {
        return $this->projectmodel->getProjectData($project);
    }
    
    private function _project_keys_get($project) {
        return $this->projectmodel->getProjectKeys($project);
    }
    
    public function project_post() {
        $data = $this->projectmodel->editProject($this->input->post());
        echo json_output($data);
    }
    
    public function project_delete($id) {
        $result = $this->projectmodel->deleteProject($id, $this->input->post('keybase_user_id'));
        echo json_output($result);
    }
    
    public function project_item_post($project)
    {
        $this->session->set_userdata('id', $this->input->post('keybase_user_id'));
        $this->load->model('projectitemmodel');
        $data = $this->projectitemmodel->loadProjectItems($project, json_decode($this->input->post('items')));
        echo json_output($data);
    }

    public function filters_get() {
        $params = $this->uri->uri_to_assoc(3, array('project', 'user', 'session'));
        $data = $this->filtermodel->getFilters($params['project'], $params['user'], $params['session']);
        foreach ($data as $index => $row) {
            $data[$index]->is_project_filter = (bool) $row->is_project_filter;
        }
        echo json_output($data);
    }
    
    public function project_filters_get() {
        $params = $this->uri->uri_to_assoc(3, array('project', 'user'));
        $data = $this->filtermodel->getProjectFilters($params['project'], $params['user']);
        echo json_output($data);
    }

    public function manage_filters_get($project=FALSE, $user=FALSE) {
        $data = $this->filtermodel->manageFilters($project);
        echo json_output($data);
    }
    
    public function filter_put($filterid) {
        $taxa = preg_split("/[\r|\n]+/", trim($this->input->get_post('taxa')));
        foreach ($taxa as $key=>$value) {
            $taxa[$key] = trim($value);
        }
        $projects = $this->input->get_post('projects');
        if (!$projects[0]) $projects = FALSE;
        $items = $this->filtermodel->getFilterItems($taxa, $projects);
        $filter = $this->filtermodel->updateFilter($filterid, $this->input->get_post('filtername'), $projects, $this->input->post('session'));
        echo json_output($filter);
    }
    
    public function filter_items_get() {
        $key = $this->input->get('key');
        $filter = $this->input->get('filter');
        if (!$key || !$filter) exit ();
        $data = $this->filtermodel->getGlobalFilterItemsForKey($filter, $key);
        echo json_output($data);
    }
    
    public function filter_items_original_get($filter) {
        if (!$filter) exit();
        $data = $this->filtermodel->getFilterItemsOrig($filter);
        echo json_output($data);
    }
    
    public function filter_post() {
        $taxa = preg_split("/[\r|\n]+/", trim($this->input->get_post('taxa')));
        foreach ($taxa as $key=>$value) {
            $taxa[$key] = trim($value);
        }
        $project = $this->input->get_post('project');
        $items = $this->filtermodel->getFilterItems($taxa, $project ? array($project) : false);
        $filter = $this->filtermodel->updateFilter(FALSE, 
                $this->input->get_post('filtername'), $project, 
                $this->input->post('session'),
                $this->input->post('isProjectFilter'));
        echo json_output($filter);
    }
    
    public function filter_get($filter) {
        
    }
    
    public function filter_delete($filter) {
        $keybase_user_id = $this->input->get_post('keybase_user_id');
        $result = $this->filtermodel->deleteFilter($filter, $keybase_user_id);
        echo json_output($result);
    }
    
    public function set_project_filter($filter) {
        // Should be combined with $this->filter_put()
        header('Access-Control-Allow-Origin: *');  
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Content-Range, Content-Disposition, Content-Description');
        header('Content-type: application/json');
        $this->filtermodel->setProjectFilter($filter, $this->input->post('is_project_filter'));
        echo json_encode($filter);
    }
    
    public function filter_meta_get($filterid) {
        if (!$filterid) exit;
        $this->load->model('filtermodel');
        $data = $this->filtermodel->getGlobalfilterMetadata($filterid);
        $data->isProjectFilter = (bool) $data->isProjectFilter;
        echo json_output($data);
    }
    
    public function filter_projects_get($filter=FALSE) {
        if (!$filter) exit;
        $this->load->model('filtermodel');
        $data = $this->filtermodel->getGlobalfilterProjects($filter);
        echo json_output($data);
    }
    
    public function filter_keys_get($filter) {
        if (!$filter) exit;
        $data = $this->filtermodel->globalFilter($filter);
        echo json_output($data);
    }
    
    public function filter_items_not_found($filter) {
        if (!$filter) exit();
        $data = $this->filtermodel->getItemsNotFound($filter);
        echo json_output($data);
    }
    
    public function users_get() {
        $data = $this->projectmodel->getUsers();
        echo json_output($data);
    }
}


/* End of file keys.php */
/* Location: ./controllers/keys.php */