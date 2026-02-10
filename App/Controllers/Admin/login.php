<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends CI_Controller {
	
	
	public $setting;
	
	function __construct(){
		
		parent::__construct();
		
		$this->load->model('m_user','user');
		
		$this->setting = $this->settings->data;	
	}
	
	public function index()
	{
		$this->load->helper(array('form', 'url'));

		$this->load->library('form_validation');

        if( $this->setting['cap_protect'] == 1 )
        $this->form_validation->set_rules('captcha' , 'تصویر امنیتی' , 'trim|xss_clean|required');

		$this->form_validation->set_rules('username', 'نام کاربری'   , 'trim|xss_clean|required|alpha_numeric|callback__username_check');
		$this->form_validation->set_rules('password', 'رمز عبور'     , 'trim|xss_clean|required');


		if ($this->form_validation->run() == FALSE)
		{
			$data = $this->setting;
            $data['_title'] = " | Login ";

			$this->load->view('admin/v_header',$data);
			$this->load->view('admin/v_login');
		}
		else
		{
            if( isset($this->user->data->level) && $this->user->data->level == 'user' )
                redirect('');
            else
			    redirect('admin/home');
		}
	}

    public function _username_check()
	{
		$data = $this->input->post(NULL,TRUE);

        if( $this->setting['cap_protect'] == 1 )
        if( ! $this->tools->checkCaptcha() )
        {
            $this->form_validation->set_message('_username_check', 'تصویر امنیتی اشتباه است');
            return FALSE;
        }

		if ($this->user->login($data))
		{
			return TRUE;
		}
		else
		{
			$this->form_validation->set_message('_username_check', 'نام کاربری یا رمز عبور اشتباه است');
		}
		return FALSE;
	}
}
?>