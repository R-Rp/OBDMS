<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller {

	public $uid = 0, $utype, $uname, $uimg;
	public function __construct()
    {
        parent::__construct();

        $this->load->model('app_model', 'app');

        if($this->session->has_userdata('uid')) {
        	$this->uid = $this->session->uid;
        	$this->utype = $this->session->utype;
        	$this->uname = $this->session->uname;
            if($this->session->uimg) {
                $this->uimg = base_url('assets/uploads/users/'.$this->uid.'/'.$this->session->uimg);
            } else {
                $this->uimg = base_url('assets/img/user-default.jpg');
            }
        	
        } else {
        	redirect(site_url('home'),'refresh');
        }

    }
	public function index()
	{
        $data['active'] = 1;
		$this->load->view('user/template/header', $data);
		$this->load->view('user/home');
		$this->load->view('user/template/footer');
	}

    public function search($group = null)
    {
        if($group) {
            $data['profiles'] = $this->app->getProfiles($group);
            $data['profiles_org'] = $this->app->getProfilesOrg($group);
            $data['group'] = $group;
            // dd($data['profiles_org']);
        }
        $data['active'] = 2;
        $this->load->view('user/template/header', $data);
        $this->load->view('user/search');
        $this->load->view('user/template/footer');
    }

    public function members($member_id = null)
    {
        if($this->input->post())
        {
            $mem_data = array(
                'name' => $this->input->post('name'),
                'blood_group_id' => $this->input->post('blood_group'),
                'phone' => $this->input->post('phone'),
                'dob' => date('Y-m-d', strtotime($this->input->post('dob'))),
                'user_id' => $this->uid,
            );
            if($member_id) {
                $result = $this->com->update('members', $mem_data, ['id' => decode($member_id)]);
            } else {
                $result = $this->com->insert('members', $mem_data);
            }
            
            if($result) {
                $this->session->set_flashdata('msg', 'Member successfully added.');
                if($member_id) $this->session->set_flashdata('msg', 'Member successfully updated.');
            } else {
                $this->session->set_flashdata('err', 'Error. Please try again later.');
            } 
            redirect(site_url('user/members'),'refresh');
        }
        $data['member'] = null; 
        if($member_id) {
            $data['member'] = $this->com->select_row('members', ['id' => decode($member_id)]);
        }
        $data['active'] = 3;
        $data['members'] = $this->app->getMembers($this->uid);
        $this->load->view('user/template/header', $data);
        $this->load->view('user/members');
        $this->load->view('user/template/footer');
    }

    public function delete_member($member_id = null)
    {
        if($member_id)
        {
            $delete = $this->com->delete('members', ['id' => decode($member_id)]);
            if($delete) {
                $this->session->set_flashdata('msg', 'Member successfully deleted.');
            } else {
                $this->session->set_flashdata('err', 'Error. Please try again later.');
            } 
            redirect(site_url('user/members'),'refresh');
        }
    }

    public function member_change_status($member_id = null, $status = 0)
    {
        $update = $this->com->update('members', ['availability' => $status], ['id' => decode($member_id)]);
        redirect(site_url('user/members'),'refresh');
    }

    public function profile()
    {
        $data['user'] = $this->app->getUser($this->uid);
        $data['donation_history'] = $this->com->select('donation_history', ['user_id' => $this->uid]);
        $data['blood_groups'] = $this->com->select('blood_groups');
        $data['states'] = $this->com->select('states');
        $data['districts'] = $this->com->select('districts');
        $this->load->view('user/template/header', $data);
        $this->load->view('user/profile');
        $this->load->view('user/template/footer');
    }

    public function profile_update()
    {
        if($this->input->post())
        {
            $this->form_validation->set_rules('name', 'name', 'required|max_length[40]|trim|xss_clean');
            $this->form_validation->set_rules('phone', 'phone', 'numeric|max_length[10]|trim|xss_clean');
            $this->form_validation->set_rules('email', 'email', 'required|valid_email|max_length[50]|trim|xss_clean');
            if ($this->form_validation->run() == TRUE)
            {

                if($this->com->get_count('users', ['email' => $this->input->post('email'), 'id !=' => $this->uid])) {
                    $this->session->set_flashdata('err', 'This email is already in use.');
                    redirect(site_url('home/register'),'refresh');
                }
                if($this->utype == 3) {
                    $data = array(
                        'name' => $this->input->post('name'),
                        'phone' => $this->input->post('phone'),
                        'email' => $this->input->post('email'),
                        'address' => $this->input->post('address'),
                        'avg_no_employees' => $this->input->post('no_employees'),
                        'state_id' => $this->input->post('state'),
                        'district_id' => $this->input->post('district'),
                    );
                } else {
                    $data = array(
                        'name' => $this->input->post('name'),
                        'blood_group_id' => $this->input->post('blood_group'),
                        'gender' => $this->input->post('gender'),
                        'dob' => date('Y-m-d', strtotime($this->input->post('dob'))),
                        'phone' => $this->input->post('phone'),
                        'email' => $this->input->post('email'),
                        'location' => $this->input->post('location'),
                        'state_id' => $this->input->post('state'),
                        'district_id' => $this->input->post('district'),
                    );
                }
                if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                    $this->load->library('upload');
                    $path = 'assets/uploads/users/'.$this->uid;
                    if (!file_exists($path)) {
                        mkdir($path, 0777, true);
                    }
                    $config['upload_path'] = $path;
                    $config['allowed_types'] = 'gif|jpg|png|bmp';
                    $config['max_size'] = '10000';
                    $config['encrypt_name'] = TRUE;
                    $this->upload->initialize($config);
                    if ($this->upload->do_upload('image')) {
                        $upl_data = $this->upload->data();
                        $data['img'] = $upl_data['file_name'];
                        $this->session->set_userdata('uimg', $upl_data['file_name']);
                    } else {
                        $this->session->set_flashdata('err', 'Failed to update image.');
                    }
                }
                
                $update = $this->com->update('users', $data, ['id' => $this->uid]);
                if($update) {
                    $this->session->set_flashdata('msg', 'Profile successfully updated.');
                    
                } else {
                    $this->session->set_flashdata('err', 'Error. Please try again later.');
                }
                redirect(site_url('user/profile'),'refresh');
            } else { 
                echo validation_errors();
            }
        }
    }

    public function change_password()
    {
        if($this->input->post())
        {
            $this->form_validation->set_rules('current_password', 'name', 'required|xss_clean');
            $this->form_validation->set_rules('password', 'name', 'required|xss_clean');
            if ($this->form_validation->run() == TRUE)
            {

                $user = $this->com->select_row('users', ['id' => $this->uid]);
                if(encode($this->input->post('current_password')) != $user->password) {
                    $this->session->set_flashdata('err', 'Incorrect current password.');
                    redirect(site_url('user/profile'),'refresh');
                }
                
                $update = $this->com->update('users', ['password' => encode($this->input->post('password'))], ['id' => $this->uid]);
                if($update) {
                    $this->session->set_flashdata('msg', 'Password changed successfully.');
                    
                } else {
                    $this->session->set_flashdata('err', 'Error. Please try again later.');
                }
                redirect(site_url('user/profile'),'refresh');
            } else { 
                echo validation_errors();
            }
        }
    }

    public function change_availability($status = 0)
    {
        $update = $this->com->update('users', ['availability' => $status], ['id' => $this->uid]);
        redirect(site_url('user/profile'),'refresh');
    }

    public function blood_donated()
    {
        if($this->input->post())
        {
            $this->form_validation->set_rules('date', 'date', 'required|xss_clean');
            if ($this->form_validation->run() == TRUE)
            {
                $ins_data = [
                    'user_id' => $this->uid,
                    'date' => date('Y-m-d', strtotime($this->input->post('date'))),
                ];
                $this->com->insert('donation_history', $ins_data);
                $this->com->update('users', ['availability' => 0], ['id' => $this->uid]);
                redirect(site_url('user'),'refresh');
            }
        }
    }

    public function users($user_id = null)
    {
        $data['active'] = 4;
        $data['users'] = $this->app->getUsers();
        if($user_id) {
            $data['user'] = $this->app->getUser(decode($user_id));
        }
        $this->load->view('user/template/header', $data);
        $this->load->view('user/users');
        $this->load->view('user/template/footer');
    }
    
    public function user_change_status($user_id = null, $status = 0, $org = 0)
    {
        $update = $this->com->update('users', ['status' => $status], ['id' => decode($user_id)]);
        if($org)
            redirect(site_url('user/organizations'),'refresh');
        else
            redirect(site_url('user/users'),'refresh');
    }

    public function organizations($user_id = null)
    {
        $data['active'] = 5;
        $data['users'] = $this->app->getOrganizations();
        if($user_id) {
            $data['user'] = $this->app->getOrg(decode($user_id));
        }
        $this->load->view('user/template/header', $data);
        $this->load->view('user/organizations');
        $this->load->view('user/template/footer');
    }

    public function contact_us()
    {
        $data['active'] = 6;
        $data['messages'] = $this->com->select('contact_us');
        $this->load->view('user/template/header', $data);
        $this->load->view('user/contact_us');
        $this->load->view('user/template/footer');
    }

    public function delete_contact_us($contact_us_id = null)
    {
        if($contact_us_id)
        {
            $delete = $this->com->delete('contact_us', ['id' => decode($contact_us_id)]);
            if($delete) {
                $this->session->set_flashdata('msg', 'Successfully deleted.');
            } else {
                $this->session->set_flashdata('err', 'Error. Please try again later.');
            } 
            redirect(site_url('user/contact_us'),'refresh');
        }
    }

    public function blood_groups()
    {
        return $this->com->select('blood_groups');
    }

    public function logout()
    {   
        $this->app->upd_log_hist($this->uid);
        $this->session->unset_userdata('uid');
        // delete_cookie('remember_me_token');
        redirect(site_url(),'refresh');
    }
}
