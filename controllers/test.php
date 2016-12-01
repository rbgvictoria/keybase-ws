<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of test
 *
 * @author nklazenga
 */
class Test extends CI_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
        $this->load->helper('form');
        $this->load->helper('json');
        $this->load->model('keymodel');
        $this->load->model('projectmodel');
        $this->load->model('filtermodel');
    }
    
    public function index() {
        
    }
    
    public function upload() {
        $data = array();
        
        $this->load->view('test_upload_view', $data);
        
        if ($this->input->post('submit')) {
            $this->load->library('KeyUploadService');
            $result = $this->keyuploadservice->loadKey($this->input->post('key_id'), $_FILES['delimitedtext']['tmp_name'], 'delimitedtext');
            echo $result;
        }
    }
}
