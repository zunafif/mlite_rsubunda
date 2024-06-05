<?php
namespace Plugins\Rawat_Jalan;

use Systems\AdminModule;
use Plugins\Icd\DB_ICD;
use Systems\Lib\Fpdf\PDF_MC_Table;
use Systems\Lib\BpjsService;

class Admin extends AdminModule
{

    protected $consid;
    protected $secretkey;
    protected $user_key;
    protected $api_url;
    protected $assign;
    
    public function init()
    {
      $this->consid = $this->settings->get('settings.BpjsConsID');
      $this->secretkey = $this->settings->get('settings.BpjsSecretKey');
      $this->user_key = $this->settings->get('settings.BpjsUserKey');
      $this->api_url = $this->settings->get('settings.BpjsApiUrl');
    }
    private $_uploads = WEBAPPS_PATH.'/berkasrawat/pages/upload';
    public function navigation()
    {
        return [
            'Kelola'              => 'index',
            'Rawat Jalan'         => 'manage',
            'Booking Registrasi'  => 'booking',
            'Booking Periksa'     => 'bookingperiksa',
            'Jadwal Dokter'       => 'jadwal'
        ];
    }

    public function getIndex()
    {
      $sub_modules = [
        ['name' => 'Rawat Jalan', 'url' => url([ADMIN, 'rawat_jalan', 'manage']), 'icon' => 'wheelchair', 'desc' => 'Pendaftaran pasien rawat jalan'],
        ['name' => 'Booking Registrasi', 'url' => url([ADMIN, 'rawat_jalan', 'booking']), 'icon' => 'file-o', 'desc' => 'Pendaftaran pasien booking rawat jalan'],
        ['name' => 'Booking Periksa', 'url' => url([ADMIN, 'rawat_jalan', 'bookingperiksa']), 'icon' => 'file-o', 'desc' => 'Booking periksa pasien rawat jalan via Online'],
        ['name' => 'Jadwal Dokter', 'url' => url([ADMIN, 'rawat_jalan', 'jadwal']), 'icon' => 'user-md', 'desc' => 'Jadwal dokter rawat jalan'],
      ];
      return $this->draw('index.html', ['sub_modules' => $sub_modules]);
    }

    public function anyManage()
    {
        $tgl_kunjungan = date('Y-m-d');
        $tgl_kunjungan_akhir = date('Y-m-d');
        $status_periksa = '';
        $status_bayar = '';

        $waapitoken =  $this->settings->get('wagateway.token');
        $waapiphonenumber =  $this->settings->get('wagateway.phonenumber');
        $nama_instansi =  $this->settings->get('settings.nama_instansi');

        if(isset($_POST['periode_rawat_jalan'])) {
          $tgl_kunjungan = $_POST['periode_rawat_jalan'];
        }
        if(isset($_POST['periode_rawat_jalan_akhir'])) {
          $tgl_kunjungan_akhir = $_POST['periode_rawat_jalan_akhir'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        if(isset($_POST['status_bayar'])) {
          $status_bayar = $_POST['status_bayar'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $cek_pcare = $this->db('mlite_modules')->where('dir', 'pcare')->oneArray();
        $master_berkas_digital = $this->core->mysql('master_berkas_digital')->toArray();
        $responsivevoice =  $this->settings->get('settings.responsivevoice');
        $this->_Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa, $status_bayar);
        return $this->draw('manage.html',
          [
            'rawat_jalan' => $this->assign,
            'cek_vclaim' => $cek_vclaim,
            'cek_pcare' => $cek_pcare,
            'master_berkas_digital' => $master_berkas_digital,
            'responsivevoice' => $responsivevoice,
            'admin_mode' => $this->settings->get('settings.admin_mode'),
            'waapitoken' => $waapitoken,
            'waapiphonenumber' => $waapiphonenumber,
            'nama_instansi' => $nama_instansi
          ]
        );
    }

    public function anyDisplay()
    {
        $tgl_kunjungan = date('Y-m-d');
        $tgl_kunjungan_akhir = date('Y-m-d');
        $status_periksa = '';
        $status_bayar = '';

        $waapitoken =  $this->settings->get('wagateway.token');
        $waapiphonenumber =  $this->settings->get('wagateway.phonenumber');
        $nama_instansi =  $this->settings->get('settings.nama_instansi');

        if(isset($_POST['periode_rawat_jalan'])) {
          $tgl_kunjungan = $_POST['periode_rawat_jalan'];
        }
        if(isset($_POST['periode_rawat_jalan_akhir'])) {
          $tgl_kunjungan_akhir = $_POST['periode_rawat_jalan_akhir'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        if(isset($_POST['status_bayar'])) {
          $status_bayar = $_POST['status_bayar'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $cek_pcare = $this->db('mlite_modules')->where('dir', 'pcare')->oneArray();
        $responsivevoice =  $this->settings->get('settings.responsivevoice');
        $this->_Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa, $status_bayar);
        echo $this->draw('display.html', ['rawat_jalan' => $this->assign, 'cek_vclaim' => $cek_vclaim, 'cek_pcare' => $cek_pcare, 'responsivevoice' => $responsivevoice, 'admin_mode' => $this->settings->get('settings.admin_mode'), 'waapitoken' => $waapitoken, 'waapiphonenumber' => $waapiphonenumber, 'nama_instansi' => $nama_instansi]);
        exit();
    }

    public function _Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa='', $status_bayar='')
    {

        if($this->settings->get('settings.responsivevoice') == 'true') {
          $this->core->addJS(url('assets/jscripts/responsivevoice.js'));
        }
        $this->_addHeaderFiles();

        $this->assign['poliklinik']     = $this->core->mysql('poliklinik')->where('status', '1')->where('kd_poli', '<>', $this->settings->get('settings.igd'))->toArray();
        $this->assign['dokter']         = $this->core->mysql('dokter')->where('status', '1')->toArray();
        $this->assign['penjab']       = $this->core->mysql('penjab')->where('status', '1')->toArray();
        $this->assign['no_rawat'] = '';
        $this->assign['no_reg']     = '';
        $this->assign['tgl_registrasi']= date('Y-m-d');
        $this->assign['jam_reg']= date('H:i:s');

        $poliklinik = str_replace(",","','", $this->core->getUserInfo('cap', null, true));
        $igd = $this->settings('settings', 'igd');
        $sql = "SELECT reg_periksa.*,
            pasien.*,
            dokter.*,
            poliklinik.*,
            penjab.*
          FROM reg_periksa, pasien, dokter, poliklinik, penjab
          WHERE reg_periksa.no_rkm_medis = pasien.no_rkm_medis
          AND reg_periksa.kd_poli != '$igd'
          AND reg_periksa.tgl_registrasi BETWEEN '$tgl_kunjungan' AND '$tgl_kunjungan_akhir'
          AND reg_periksa.kd_dokter = dokter.kd_dokter
          AND reg_periksa.kd_poli = poliklinik.kd_poli
          AND reg_periksa.kd_pj = penjab.kd_pj";

        if ($this->core->getUserInfo('role') != 'admin') {
          $sql .= " AND reg_periksa.kd_poli IN ('$poliklinik')";
        }
        if($status_periksa == 'belum') {
          $sql .= " AND reg_periksa.stts = 'Belum'";
        }
        if($status_periksa == 'selesai') {
          $sql .= " AND reg_periksa.stts = 'Sudah'";
        }
        if($status_periksa == 'lunas') {
          $sql .= " AND reg_periksa.status_bayar = 'Sudah Bayar'";
        }

        $stmt = $this->core->mysql()->pdo()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $this->assign['list'] = [];
        foreach ($rows as $row) {
          $bridging_sep = $this->core->mysql('bridging_sep')->where('no_rawat', $row['no_rawat'])->oneArray();
          $row['no_sep'] = isset_or($bridging_sep['no_sep']);
          $this->assign['list'][] = $row;
        }

        if (isset($_POST['no_rawat'])){
          $this->assign['reg_periksa'] = $this->core->mysql('reg_periksa')
            ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
            ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
            ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
            ->join('penjab', 'penjab.kd_pj=reg_periksa.kd_pj')
            ->where('no_rawat', $_POST['no_rawat'])
            ->oneArray();
        } else {
          $this->assign['reg_periksa'] = [
            'no_rkm_medis' => '',
            'nm_pasien' => '',
            'no_reg' => '',
            'no_rawat' => '',
            'tgl_registrasi' => '',
            'jam_reg' => '',
            'kd_dokter' => '',
            'no_rm' => '',
            'kd_poli' => '',
            'p_jawab' => '',
            'almt_pj' => '',
            'hubunganpj' => '',
            'biaya_reg' => '',
            'stts' => '',
            'stts_daftar' => '',
            'status_lanjut' => '',
            'kd_pj' => '',
            'umurdaftar' => '',
            'sttsumur' => '',
            'status_bayar' => '',
            'status_poli' => '',
            'nm_pasien' => '',
            'tgl_lahir' => '',
            'jk' => '',
            'alamat' => '',
            'no_tlp' => '',
            'pekerjaan' => ''
          ];
        }
    }

    public function anyForm()
    {

      $this->assign['poliklinik'] = $this->core->mysql('poliklinik')->where('kd_poli', '<>', $this->settings->get('settings.igd'))->where('status', '1')->toArray();
      $this->assign['dokter'] = $this->core->mysql('dokter')->where('status', '1')->toArray();
      $this->assign['penjab'] = $this->core->mysql('penjab')->where('status', '1')->toArray();
      $date = date('Y-m-d');
      $jam = date('H:i:s');
      if (isset($_POST['no_rawat'])){
        $this->assign['reg_periksa'] = $this->core->mysql('reg_periksa')
          ->select('pasien.no_rkm_medis')
          ->select('pasien.nm_pasien')
          ->select('pasien.tgl_lahir')
          ->select('pasien.jk')
          ->select('pasien.no_tlp')
          ->select('reg_periksa.tgl_registrasi')
          ->select('reg_periksa.jam_reg')
          ->select('reg_periksa.no_rawat')
          ->select('reg_periksa.no_reg')
          ->select('poliklinik.kd_poli')
          ->select('dokter.kd_dokter')
          ->select('penjab.kd_pj')
          ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
          ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
          ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
          ->join('penjab', 'penjab.kd_pj=reg_periksa.kd_pj')
          ->where('no_rawat', $_POST['no_rawat'])
          ->oneArray();
        echo $this->draw('form.html', [
          'rawat_jalan' => $this->assign
        ]);
      } else {
        $this->assign['reg_periksa'] = [
          'no_rkm_medis' => '',
          'nm_pasien' => '',
          'no_reg' => '',
          'no_rawat' => '',
          'tgl_registrasi' => '',
          'jam_reg' => '',
          'kd_dokter' => '',
          'no_rm' => '',
          'kd_poli' => '',
          'p_jawab' => '',
          'almt_pj' => '',
          'hubunganpj' => '',
          'biaya_reg' => '',
          'stts' => '',
          'stts_daftar' => '',
          'status_lanjut' => '',
          'kd_pj' => '',
          'umurdaftar' => '',
          'sttsumur' => '',
          'status_bayar' => '',
          'status_poli' => '',
          'nm_pasien' => '',
          'tgl_lahir' => '',
          'jk' => '',
          'alamat' => '',
          'no_tlp' => '',
          'pekerjaan' => ''
        ];
        echo $this->draw('form.html', [
          'rawat_jalan' => $this->assign
        ]);
      }
      exit();
    }

    public function anyStatusDaftar()
    {
      if(isset($_POST['no_rkm_medis'])) {
        $rawat = $this->core->mysql('reg_periksa')
          ->where('no_rkm_medis', $_POST['no_rkm_medis'])
          ->where('status_bayar', 'Belum Bayar')
          ->limit(1)
          ->oneArray();
          if($rawat) {
            $stts_daftar = "Transaki tanggal ".date('Y-m-d', strtotime($rawat['tgl_registrasi']))." belum diselesaikan" ;
            $stts_daftar_hidden = $stts_daftar;
            if($this->settings->get('settings.cekstatusbayar') == 'false'){
              $stts_daftar_hidden = 'Lama';
            }
            $bg_status = 'has-error';
          } else {
            $result = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $_POST['no_rkm_medis'])->oneArray();
            if(!empty($result['no_rawat'])) {
              $stts_daftar = 'Lama';
              $bg_status = 'has-info';
              $stts_daftar_hidden = $stts_daftar;
            } else {
              $stts_daftar = 'Baru';
              $bg_status = 'has-success';
              $stts_daftar_hidden = $stts_daftar;
            }
          }
        echo $this->draw('stts.daftar.html', ['stts_daftar' => $stts_daftar, 'stts_daftar_hidden' => $stts_daftar_hidden, 'bg_status' =>$bg_status]);
      } else {
        $rawat = $this->core->mysql('reg_periksa')
          ->where('no_rawat', $_POST['no_rawat'])
          ->oneArray();
        echo $this->draw('stts.daftar.html', ['stts_daftar' => $rawat['stts_daftar']]);
      }
      exit();
    }

    public function postSave()
    {
      if ($_POST['tgl_registrasi'] > date('Y-m-d')) {
        $this->core->mysql('booking_registrasi')->save([
          'tanggal_booking' => date('Y-m-d'),
          'jam_booking' => date('H:i:s'),
          'no_rkm_medis' => $_POST['no_rkm_medis'],
          'tanggal_periksa' => $_POST['tgl_registrasi'],
          'kd_dokter' => $_POST['kd_dokter'],
          'kd_poli' => $_POST['kd_poli'],
          'no_reg' => $this->core->setNoBooking($_POST['kd_dokter'], $_POST['tgl_registrasi'], $_POST['kd_poli']),
          'kd_pj' => $_POST['kd_pj'],
          'limit_reg' => '0',
          'waktu_kunjungan' => $_POST['tgl_registrasi'] . ' ' . $_POST['jam_reg'],
          'status' => 'Belum'
        ]);
      } else if (!$this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {

        $_POST['status_lanjut'] = 'Ralan';
        $_POST['stts'] = 'Belum';
        $_POST['status_bayar'] = 'Belum Bayar';
        $_POST['p_jawab'] = '-';
        $_POST['almt_pj'] = '-';
        $_POST['hubunganpj'] = '-';

        $poliklinik = $this->core->mysql('poliklinik')->where('kd_poli', $_POST['kd_poli'])->oneArray();

        $_POST['biaya_reg'] = $poliklinik['registrasi'];

        $pasien = $this->core->mysql('pasien')->where('no_rkm_medis', $_POST['no_rkm_medis'])->oneArray();

      	$birthDate = new \DateTime($pasien['tgl_lahir']);
      	$today = new \DateTime("today");
      	$umur_daftar = "0";
        $status_umur = 'Hr';
        if ($birthDate < $today) {
        	$y = $today->diff($birthDate)->y;
        	$m = $today->diff($birthDate)->m;
        	$d = $today->diff($birthDate)->d;
          $umur_daftar = $d;
          $status_umur = "Hr";
          if($y !='0'){
            $umur_daftar = $y;
            $status_umur = "Th";
          }
          if($y =='0' && $m !='0'){
            $umur_daftar = $m;
            $status_umur = "Bl";
          }
        }

        $_POST['umurdaftar'] = $umur_daftar;
        $_POST['sttsumur'] = $status_umur;
        $_POST['status_poli'] = 'Lama';

        $query = $this->core->mysql('reg_periksa')->save($_POST);
      } else {
        $query = $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->save([
          'kd_poli' => $_POST['kd_poli'],
          'kd_dokter' => $_POST['kd_dokter'],
          'kd_pj' => $_POST['kd_pj']
        ]);
      }

      if($query) {
        $data['status'] = 'success';
        echo json_encode($data);
      } else {
        $data['status'] = 'error';
        echo json_encode($data);
      }

      exit();
    }

    public function anyBooking($page = 1)
    {

      $this->core->addCSS(url('assets/css/jquery-ui.css'));
      $this->core->addCSS(url('assets/css/jquery.timepicker.css'));

      // JS
      $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
      $this->core->addJS(url('assets/jscripts/jquery.timepicker.js'), 'footer');

      $waapitoken =  $this->settings->get('wagateway.token');
      $waapiphonenumber =  $this->settings->get('wagateway.phonenumber');
      $nama_instansi =  $this->settings->get('settings.nama_instansi');

      if (isset($_POST['valid'])) {
          if (isset($_POST['no_rkm_medis']) && !empty($_POST['no_rkm_medis'])) {
              foreach ($_POST['no_rkm_medis'] as $item) {

                  $row = $this->core->mysql('booking_registrasi')->where('no_rkm_medis', $item)->where('tanggal_periksa', date('Y-m-d'))->oneArray();

                  $cek_stts_daftar = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $item)->count();
                  $_POST['stts_daftar'] = 'Baru';
                  if($cek_stts_daftar > 0) {
                    $_POST['stts_daftar'] = 'Lama';
                  }

                  $biaya_reg = $this->core->mysql('poliklinik')->where('kd_poli', $row['kd_poli'])->oneArray();
                  $_POST['biaya_reg'] = $biaya_reg['registrasi'];
                  if($_POST['stts_daftar'] == 'Lama') {
                    $_POST['biaya_reg'] = $biaya_reg['registrasilama'];
                  }

                  $cek_status_poli = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $item)->where('kd_poli', $row['kd_poli'])->count();
                  $_POST['status_poli'] = 'Baru';
                  if($cek_status_poli > 0) {
                    $_POST['status_poli'] = 'Lama';
                  }

                  // set umur
                  $tanggal = new \DateTime($this->getPasienInfo('tgl_lahir', $item));
                  $today = new \DateTime(date('Y-m-d'));
                  $y = $today->diff($tanggal)->y;
                  $m = $today->diff($tanggal)->m;
                  $d = $today->diff($tanggal)->d;

                  $umur="0";
                  $sttsumur="Th";
                  if($y>0){
                      $umur=$y;
                      $sttsumur="Th";
                  }else if($y==0){
                      if($m>0){
                          $umur=$m;
                          $sttsumur="Bl";
                      }else if($m==0){
                          $umur=$d;
                          $sttsumur="Hr";
                      }
                  }

                  if($row['status'] == 'Belum') {
                    $insert = $this->core->mysql('reg_periksa')
                      ->save([
                        'no_reg' => $row['no_reg'],
                        'no_rawat' => $this->setNoRawat(),
                        'tgl_registrasi' => date('Y-m-d'),
                        'jam_reg' => date('H:i:s'),
                        'kd_dokter' => $row['kd_dokter'],
                        'no_rkm_medis' => $item,
                        'kd_poli' => $row['kd_poli'],
                        'p_jawab' => $this->getPasienInfo('namakeluarga', $item),
                        'almt_pj' => $this->getPasienInfo('alamatpj', $item),
                        'hubunganpj' => $this->getPasienInfo('keluarga', $item),
                        'biaya_reg' => $_POST['biaya_reg'],
                        'stts' => 'Belum',
                        'stts_daftar' => $_POST['stts_daftar'],
                        'status_lanjut' => 'Ralan',
                        'kd_pj' => $row['kd_pj'],
                        'umurdaftar' => $umur,
                        'sttsumur' => $sttsumur,
                        'status_bayar' => 'Belum Bayar',
                        'status_poli' => $_POST['status_poli']
                      ]);

                      if ($insert) {
                          $this->core->mysql('booking_registrasi')->where('no_rkm_medis', $item)->where('tanggal_periksa', date('Y-m-d'))->update('status', 'Terdaftar');
                          $this->notify('success', 'Validasi sukses');
                      } else {
                          $this->notify('failure', 'Validasi gagal');
                      }
                  }
              }

              redirect(url([ADMIN, 'rawat_jalan', 'booking']));
          }
      }

      $this->_addHeaderFiles();
      $start_date = date('Y-m-d');
      if(isset($_GET['start_date']) && $_GET['start_date'] !='')
        $start_date = $_GET['start_date'];
      $end_date = date('Y-m-d');
      if(isset($_GET['end_date']) && $_GET['end_date'] !='')
        $end_date = $_GET['end_date'];
      $perpage = '10';
      $phrase = '';
      if(isset($_GET['s']))
        $phrase = $_GET['s'];

      // pagination
      $totalRecords = $this->core->mysql()->pdo()->prepare("SELECT booking_registrasi.no_rkm_medis FROM booking_registrasi, pasien WHERE booking_registrasi.no_rkm_medis = pasien.no_rkm_medis AND (booking_registrasi.no_rkm_medis LIKE ? OR pasien.nm_pasien LIKE ?) AND booking_registrasi.tanggal_periksa BETWEEN '$start_date' AND '$end_date'");
      $totalRecords->execute(['%'.$phrase.'%', '%'.$phrase.'%']);
      $totalRecords = $totalRecords->fetchAll();

      $pagination = new \Systems\Lib\Pagination($page, count($totalRecords), $perpage, url([ADMIN, 'rawat_jalan', 'booking', '%d?s='.$phrase.'&start_date='.$start_date.'&end_date='.$end_date]));
      $this->assign['pagination'] = $pagination->nav('pagination','5');
      $this->assign['totalRecords'] = $totalRecords;

      $offset = $pagination->offset();
      $query = $this->core->mysql()->pdo()->prepare("SELECT booking_registrasi.*, pasien.nm_pasien, pasien.alamat, pasien.no_tlp, dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab, pasien.no_peserta FROM booking_registrasi, pasien, dokter, poliklinik, penjab WHERE booking_registrasi.no_rkm_medis = pasien.no_rkm_medis AND booking_registrasi.kd_dokter = dokter.kd_dokter AND booking_registrasi.kd_poli = poliklinik.kd_poli AND booking_registrasi.kd_pj = penjab.kd_pj AND (booking_registrasi.no_rkm_medis LIKE ? OR pasien.nm_pasien LIKE ?) AND booking_registrasi.tanggal_periksa BETWEEN '$start_date' AND '$end_date' LIMIT $perpage OFFSET $offset");
      $query->execute(['%'.$phrase.'%', '%'.$phrase.'%']);
      $rows = $query->fetchAll();

      $this->assign['list'] = [];
      if (count($rows)) {
          foreach ($rows as $row) {
              $row = htmlspecialchars_array($row);
              $this->assign['list'][] = $row;
          }
      }

      $this->assign['searchUrl'] =  url([ADMIN, 'rawat_jalan', 'booking', $page.'?s='.$phrase.'&start_date='.$start_date.'&end_date='.$end_date]);
      return $this->draw('booking.html', ['booking' => $this->assign, 'waapitoken' => $waapitoken, 'waapiphonenumber' => $waapiphonenumber, 'nama_instansi' => $nama_instansi]);

    }

    public function getBookingPeriksa()
    {
        $date = date('Y-m-d');
        $text = 'Booking Periksa';

        // CSS
        $this->core->addCSS(url('assets/css/jquery-ui.css'));
        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
        // JS
        $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'), 'footer');

        return $this->draw('booking.periksa.html',
          [
            'text' => $text,
            'waapitoken' => $this->settings->get('wagateway.token'),
            'waapiphonenumber' => $this->settings->get('wagateway.phonenumber'),
            'nama_instansi' => $this->settings->get('settings.nama_instansi'),
            'booking' => $this->core->mysql('booking_periksa')
              ->select([
                'no_booking' => 'booking_periksa.no_booking',
                'tanggal' => 'booking_periksa.tanggal',
                'nama' => 'booking_periksa.nama',
                'no_telp' => 'booking_periksa.no_telp',
                'alamat' => 'booking_periksa.alamat',
                'email' => 'booking_periksa.email',
                'nm_poli' => 'poliklinik.nm_poli',
                'status' => 'booking_periksa.status',
                'tanggal_booking' => 'booking_periksa.tanggal_booking'
              ])
              ->join('poliklinik', 'poliklinik.kd_poli = booking_periksa.kd_poli')
              //->where('tambahan_pesan', 'jkn_mobile_v2')
              ->toArray()
          ]
        );
    }

    public function postSaveBookingPeriksa()
    {
      $this->core->mysql('booking_periksa')->where('no_booking', $_POST['no_booking'])->save(['status' => $_POST['status']]);
      $this->core->mysql('booking_periksa_balasan')
      ->save([
        'no_booking' => $_POST['no_booking'],
        'balasan' => $_POST['message']
      ]);
      exit();
    }

    public function anyKontrol()
    {
      $rows = $this->core->mysql('booking_registrasi')
        ->join('poliklinik', 'poliklinik.kd_poli=booking_registrasi.kd_poli')
        ->join('dokter', 'dokter.kd_dokter=booking_registrasi.kd_dokter')
        ->join('penjab', 'penjab.kd_pj=booking_registrasi.kd_pj')
        ->where('no_rkm_medis', $_POST['no_rkm_medis'])
        ->toArray();
      $i = 1;
      $result = [];
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $result[] = $row;
      }
      echo $this->draw('kontrol.html', ['booking_registrasi' => $result]);
      exit();
    }

    public function postSaveKontrol()
    {

      $query = $this->core->mysql('skdp_bpjs')->save([
        'tahun' => date('Y'),
        'no_rkm_medis' => $_POST['no_rkm_medis'],
        'diagnosa' => $_POST['diagnosa'],
        'terapi' => $_POST['terapi'],
        'alasan1' => $_POST['alasan1'],
        'alasan2' => '',
        'rtl1' => $_POST['rtl1'],
        'rtl2' => '',
        'tanggal_datang' => $_POST['tanggal_datang'],
        'tanggal_rujukan' => $_POST['tanggal_rujukan'],
        'no_antrian' => $this->core->setNoSKDP(),
        'kd_dokter' => $this->core->getRegPeriksaInfo('kd_dokter', $_POST['no_rawat']),
        'status' => 'Menunggu'
      ]);

      if ($query) {
        $this->core->mysql('booking_registrasi')
          ->save([
            'tanggal_booking' => date('Y-m-d'),
            'jam_booking' => date('H:i:s'),
            'no_rkm_medis' => $_POST['no_rkm_medis'],
            'tanggal_periksa' => $_POST['tanggal_datang'],
            'kd_dokter' => $this->core->getRegPeriksaInfo('kd_dokter', $_POST['no_rawat']),
            'kd_poli' => $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']),
            'no_reg' => $this->core->setNoBooking($this->core->getRegPeriksaInfo('kd_dokter', $_POST['no_rawat']), $_POST['tanggal_datang'], $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat'])),
            'kd_pj' => $this->core->getRegPeriksaInfo('kd_pj', $_POST['no_rawat']),
            'limit_reg' => 0,
            'waktu_kunjungan' => $_POST['tanggal_datang'].' '.date('H:i:s'),
            'status' => 'Belum'
          ]);
      }

      exit();
    }

    public function postSaveKontrolBPJS()
    {

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consid . $this->secretkey . $tStamp;
      $_POST['sep_user']  = $this->core->getUserInfo('fullname', null, true);

      $maping_dokter_dpjpvclaim = $this->core->mysql('maping_dokter_dpjpvclaim')->where('kd_dokter', $this->core->getRegPeriksaInfo('kd_dokter', $_POST['no_rawat']))->oneArray();
      $maping_poli_bpjs = $this->core->mysql('maping_poli_bpjs')->where('kd_poli_rs', $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']))->oneArray();
      $get_sep = $this->core->mysql('bridging_sep')->where('no_rawat', $_POST['no_rawat'])->oneArray();
      $_POST['no_sep'] = $get_sep['no_sep'];
      $get_sep_internal = $this->core->mysql('bridging_sep_internal')->where('no_rawat', $_POST['no_rawat'])->oneArray();

      if(empty($get_sep['no_sep'])) {
        $_POST['no_sep'] = $get_sep_internal['no_sep'];
      }

      $data = [
        'request' => [
          'noSEP' => $_POST['no_sep'],
          'kodeDokter' => $maping_dokter_dpjpvclaim['kd_dokter_bpjs'],
          'poliKontrol' => $maping_poli_bpjs['kd_poli_bpjs'],
          'tglRencanaKontrol' => $_POST['tanggal_datang'],
          'user' => $_POST['sep_user']
        ]
      ];
      $statusUrl = 'insert';
      $method = 'post';

      $data = json_encode($data);

      $url = $this->api_url . 'RencanaKontrol/' . $statusUrl;
      $output = BpjsService::$method($url, $data, $this->consid, $this->secretkey, $this->user_key, $tStamp);
      $data = json_decode($output, true);
      //echo $data['metaData']['message'];
      if ($data == NULL) {
        echo 'Koneksi ke server BPJS terputus. Silahkan ulangi beberapa saat lagi!';
      } else if ($data['metaData']['code'] == 200) {
        $stringDecrypt = stringDecrypt($key, $data['response']);
        $decompress = '""';
        $decompress = decompress($stringDecrypt);
        $spri = json_decode($decompress, true);
        //echo $spri['noSuratKontrol'];

        $bridging_surat_pri_bpjs = $this->core->mysql('bridging_surat_kontrol_bpjs')->save([
          'no_sep' => $_POST['no_sep'],
          'tgl_surat' => $_POST['tanggal_rujukan'],
          'no_surat' => $spri['noSuratKontrol'],
          'tgl_rencana' => $_POST['tanggal_datang'],
          'kd_dokter_bpjs' => $maping_dokter_dpjpvclaim['kd_dokter_bpjs'],
          'nm_dokter_bpjs' => $maping_dokter_dpjpvclaim['nm_dokter_bpjs'],
          'kd_poli_bpjs' => $maping_poli_bpjs['kd_poli_bpjs'],
          'nm_poli_bpjs' => $maping_poli_bpjs['nm_poli_bpjs']
        ]);

      }

      exit();
    }

    public function postHapusKontrol()
    {
      $this->core->mysql('booking_registrasi')->where('kd_dokter', $_POST['kd_dokter'])->where('no_rkm_medis', $_POST['no_rkm_medis'])->where('tanggal_periksa', $_POST['tanggal_periksa'])->where('status', 'Belum')->delete();
      $this->core->mysql('skdp_bpjs')->where('kd_dokter', $_POST['kd_dokter'])->where('no_rkm_medis', $_POST['no_rkm_medis'])->where('tanggal_datang', $_POST['tanggal_periksa'])->where('status', 'Menunggu')->delete();
      exit();
    }

    public function getJadwal()
    {
        // JS
        $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/jquery.timepicker.js'), 'footer');
        $this->_addHeaderFiles();
        $rows = $this->core->mysql('jadwal')->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')->toArray();
        $this->assign['jadwal'] = [];
        foreach ($rows as $row) {
            $row['delURL'] = url([ADMIN, 'rawat_jalan', 'jadwaldel', $row['kd_dokter'], $row['hari_kerja']]);
            $row['editURL'] = url([ADMIN, 'rawat_jalan', 'jadwaledit', $row['kd_dokter'], $row['hari_kerja']]);
            $this->assign['jadwal'][] = $row;
        }

        return $this->draw('jadwal.html', ['pendaftaran' => $this->assign]);
    }

    public function getJadwalDel($kd_dokter, $hari_kerja)
    {
        if ($pendaftaran = $this->core->mysql('jadwal')->where('kd_dokter', $kd_dokter)->where('hari_kerja', $hari_kerja)->oneArray()) {
            if ($this->core->mysql('jadwal')->where('kd_dokter', $kd_dokter)->where('hari_kerja', $hari_kerja)->delete()) {
                $this->notify('success', 'Hapus sukses');
            } else {
                $this->notify('failure', 'Hapus gagal');
            }
        }
        redirect(url([ADMIN, 'rawat_jalan', 'jadwal']));
    }

    public function getJadwalAdd()
    {
        $this->core->addCSS(url('assets/css/jquery-ui.css'));
        $this->core->addCSS(url('assets/css/jquery.timepicker.css'));

        // JS
        $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/jquery.timepicker.js'), 'footer');
        $this->_addHeaderFiles();
        if (!empty($redirectData = getRedirectData())) {
            $this->assign['form'] = filter_var_array($redirectData, FILTER_SANITIZE_STRING);
        } else {
            $this->assign['form'] = [
              'kd_dokter' => '',
              'hari_kerja' => '',
              'jam_mulai' => '',
              'jam_selesai' => '',
              'kd_poli' => '',
              'kuota' => ''
            ];
        }
        $this->assign['title'] = 'Tambah Jadwal Dokter';
        $this->assign['dokter'] = $this->core->mysql('dokter')->toArray();
        $this->assign['poliklinik'] = $this->core->mysql('poliklinik')->toArray();
        $this->assign['hari_kerja'] = $this->core->getEnum('jadwal', 'hari_kerja');
        $this->assign['postUrl'] = url([ADMIN, 'rawat_jalan', 'jadwalsave', $this->assign['form']['kd_dokter'], $this->assign['form']['hari_kerja']]);
        return $this->draw('jadwal.form.html', ['pendaftaran' => $this->assign]);
    }

    public function getJadwalEdit($id, $hari_kerja)
    {
        $this->core->addCSS(url('assets/css/jquery-ui.css'));
        $this->core->addCSS(url('assets/css/jquery.timepicker.css'));

        // JS
        $this->core->addJS(url('assets/jscripts/jquery-ui.js'), 'footer');
        $this->core->addJS(url('assets/jscripts/jquery.timepicker.js'), 'footer');
        $this->_addHeaderFiles();
        $row = $this->core->mysql('jadwal')->where('kd_dokter', $id)->where('hari_kerja', $hari_kerja)->oneArray();
        if (!empty($row)) {
            $this->assign['form'] = $row;
            $this->assign['title'] = 'Edit Jadwal';
            $this->assign['hari_kerja'] = $this->core->getEnum('jadwal', 'hari_kerja');
            $this->assign['dokter'] = $this->core->mysql('dokter')->toArray();
            $this->assign['poliklinik'] = $this->core->mysql('poliklinik')->toArray();

            $this->assign['postUrl'] = url([ADMIN, 'rawat_jalan', 'jadwalsave', $this->assign['form']['kd_dokter'], $this->assign['form']['hari_kerja']]);
            return $this->draw('jadwal.form.html', ['pendaftaran' => $this->assign]);
        } else {
            redirect(url([ADMIN, 'rawat_jalan', 'jadwal']));
        }
    }

    public function postJadwalSave($id = null, $hari_kerja = null)
    {
        $errors = 0;

        if (!$id) {
            $location = url([ADMIN, 'rawat_jalan', 'jadwal']);
        } else {
            $location = url([ADMIN, 'rawat_jalan', 'jadwaledit', $_POST['kd_dokter'], $_POST['hari_kerja']]);
        }

        if (checkEmptyFields(['kd_dokter', 'hari_kerja', 'kd_poli'], $_POST)) {
            $this->notify('failure', 'Isian kosong');
            redirect($location, $_POST);
        }

        if (!$errors) {
            unset($_POST['save']);

            if (!$id) {    // new
                $query = $this->core->mysql('jadwal')->save($_POST);
            } else {        // edit
                $query = $this->core->mysql('jadwal')->where('kd_dokter', $id)->where('hari_kerja', $hari_kerja)->save($_POST);
            }

            if ($query) {
                $this->notify('success', 'Simpan sukes');
            } else {
                $this->notify('failure', 'Simpan gagal');
            }

            redirect($location);
        }

        redirect($location, $_POST);
    }

    public function postStatusRawat()
    {
      $datetime = date('Y-m-d H:i:s');
      $cek = $this->core->mysql('mutasi_berkas')->where('no_rawat', $_POST['no_rawat'])->oneArray();
      if($_POST['stts'] == 'Berkas Dikirim') {
          if(!$this->core->mysql('mutasi_berkas')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
            $this->core->mysql('mutasi_berkas')->save([
              'no_rawat' => $_POST['no_rawat'],
              'status' => 'Sudah Dikirim',
              'dikirim' => $datetime,
              'diterima' => '0000-00-00 00:00:00',
              'kembali' => '0000-00-00 00:00:00',
              'tidakada' => '0000-00-00 00:00:00',
              'ranap' => '0000-00-00 00:00:00'
            ]);
          }
      } else if ($_POST['stts'] == 'Berkas Diterima') {
          if(!$this->core->mysql('mutasi_berkas')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
            $this->core->mysql('mutasi_berkas')->save([
              'no_rawat' => $_POST['no_rawat'],
              'status' => 'Sudah Diterima',
              'dikirim' => $datetime,
              'diterima' => $datetime,
              'kembali' => '0000-00-00 00:00:00',
              'tidakada' => '0000-00-00 00:00:00',
              'ranap' => '0000-00-00 00:00:00'
            ]);
          } else {
            $this->core->mysql('mutasi_berkas')->where('no_rawat', $_POST['no_rawat'])->save([
              'status' => 'Sudah Diterima',
              'diterima' => $datetime
            ]);
          }
          $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->save($_POST);
      } else {
          $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->save($_POST);
      }
      exit();
    }

    public function postStatusLanjut()
    {
      $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->save([
        'status_lanjut' => 'Ranap'
      ]);
      exit();
    }

    public function anyPasien()
    {
      if(isset($_POST['cari'])) {
        $pasien = $this->core->mysql('pasien')
          ->like('no_rkm_medis', '%'.$_POST['cari'].'%')
          ->orLike('nm_pasien', '%'.$_POST['cari'].'%')
          ->asc('no_rkm_medis')
          ->limit(5)
          ->toArray();
      }
      echo $this->draw('pasien.html', ['pasien' => $pasien]);
      exit();
    }

    public function getAntrian()
    {
      $settings = $this->settings('settings');
      $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));
      $rawat_jalan = $this->core->mysql('reg_periksa')
        ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
        ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
        ->join('dokter', 'dokter.kd_dokter=reg_periksa.kd_dokter')
        ->join('penjab', 'penjab.kd_pj=reg_periksa.kd_pj')
        ->where('no_rawat', $_GET['no_rawat'])
        ->oneArray();
      echo $this->draw('antrian.html', ['rawat_jalan' => $rawat_jalan]);
      exit();
    }

    public function postHapus()
    {
      $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->delete();
      exit();
    }

    public function postSaveDetail()
    {
      if($_POST['kat'] == 'tindakan') {
        $jns_perawatan = $this->core->mysql('jns_perawatan')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->oneArray();
        if($_POST['provider'] == 'rawat_jl_dr') {
          $this->core->mysql('rawat_jl_dr')->save([
            'no_rawat' => $_POST['no_rawat'],
            'kd_jenis_prw' => $_POST['kd_jenis_prw'],
            'kd_dokter' => $_POST['kode_provider'],
            'tgl_perawatan' => $_POST['tgl_perawatan'],
            'jam_rawat' => $_POST['jam_rawat'],
            'material' => $jns_perawatan['material'],
            'bhp' => $jns_perawatan['bhp'],
            'tarif_tindakandr' => $jns_perawatan['tarif_tindakandr'],
            'kso' => $jns_perawatan['kso'],
            'menejemen' => $jns_perawatan['menejemen'],
            'biaya_rawat' => $jns_perawatan['total_byrdr'],
            'stts_bayar' => 'Belum'
          ]);
        }
        if($_POST['provider'] == 'rawat_jl_pr') {
          $this->core->mysql('rawat_jl_pr')->save([
            'no_rawat' => $_POST['no_rawat'],
            'kd_jenis_prw' => $_POST['kd_jenis_prw'],
            'nip' => $_POST['kode_provider2'],
            'tgl_perawatan' => $_POST['tgl_perawatan'],
            'jam_rawat' => $_POST['jam_rawat'],
            'material' => $jns_perawatan['material'],
            'bhp' => $jns_perawatan['bhp'],
            'tarif_tindakanpr' => $jns_perawatan['tarif_tindakanpr'],
            'kso' => $jns_perawatan['kso'],
            'menejemen' => $jns_perawatan['menejemen'],
            'biaya_rawat' => $jns_perawatan['total_byrpr'],
            'stts_bayar' => 'Belum'
          ]);
        }
        if($_POST['provider'] == 'rawat_jl_drpr') {
          $this->core->mysql('rawat_jl_drpr')->save([
            'no_rawat' => $_POST['no_rawat'],
            'kd_jenis_prw' => $_POST['kd_jenis_prw'],
            'kd_dokter' => $_POST['kode_provider'],
            'nip' => $_POST['kode_provider2'],
            'tgl_perawatan' => $_POST['tgl_perawatan'],
            'jam_rawat' => $_POST['jam_rawat'],
            'material' => $jns_perawatan['material'],
            'bhp' => $jns_perawatan['bhp'],
            'tarif_tindakandr' => $jns_perawatan['tarif_tindakandr'],
            'tarif_tindakanpr' => $jns_perawatan['tarif_tindakanpr'],
            'kso' => $jns_perawatan['kso'],
            'menejemen' => $jns_perawatan['menejemen'],
            'biaya_rawat' => $jns_perawatan['total_byrdrpr'],
            'stts_bayar' => 'Belum'
          ]);
        }
      }
      exit();
    }

    public function postHapusDetail()
    {
      if($_POST['provider'] == 'rawat_jl_dr') {
        $this->core->mysql('rawat_jl_dr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      if($_POST['provider'] == 'rawat_jl_pr') {
        $this->core->mysql('rawat_jl_pr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      if($_POST['provider'] == 'rawat_jl_drpr') {
        $this->core->mysql('rawat_jl_drpr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      exit();
    }

    public function anyRincian()
    {
      $rows_rawat_jl_dr = $this->core->mysql('rawat_jl_dr')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $rows_rawat_jl_pr = $this->core->mysql('rawat_jl_pr')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $rows_rawat_jl_drpr = $this->core->mysql('rawat_jl_drpr')->where('no_rawat', $_POST['no_rawat'])->toArray();

      $jumlah_total = 0;
      $rawat_jl_dr = [];
      $rawat_jl_pr = [];
      $rawat_jl_drpr = [];
      $i = 1;

      if($rows_rawat_jl_dr) {
        foreach ($rows_rawat_jl_dr as $row) {
          $jns_perawatan = $this->core->mysql('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_dr';
          $rawat_jl_dr[] = $row;
        }
      }

      if($rows_rawat_jl_pr) {
        foreach ($rows_rawat_jl_pr as $row) {
          $jns_perawatan = $this->core->mysql('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_pr';
          $rawat_jl_pr[] = $row;
        }
      }

      if($rows_rawat_jl_drpr) {
        foreach ($rows_rawat_jl_drpr as $row) {
          $jns_perawatan = $this->core->mysql('jns_perawatan')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_jl_drpr';
          $rawat_jl_drpr[] = $row;
        }
      }

      echo $this->draw('rincian.html', ['rawat_jl_dr' => $rawat_jl_dr, 'rawat_jl_pr' => $rawat_jl_pr, 'rawat_jl_drpr' => $rawat_jl_drpr, 'jumlah_total' => $jumlah_total, 'no_rawat' => $_POST['no_rawat']]);
      exit();
    }

    public function anySoap()
    {

      $prosedurs = $this->core->mysql('prosedur_pasien')
         ->where('no_rawat', $_POST['no_rawat'])
         ->asc('prioritas')
         ->toArray();
       $prosedur = [];
       foreach ($prosedurs as $row) {
         $icd9 = $this->core->mysql('icd9')->where('kode', $row['kode'])->oneArray();
         $row['nama'] = $icd9['deskripsi_panjang'];
         $prosedur[] = $row;
       }
       $diagnosas = $this->core->mysql('diagnosa_pasien')
         ->where('no_rawat', $_POST['no_rawat'])
         ->asc('prioritas')
         ->toArray();
       $diagnosa = [];
       foreach ($diagnosas as $row) {
         $icd10 = $this->core->mysql('penyakit')->where('kd_penyakit', $row['kd_penyakit'])->oneArray();
         $row['nama'] = $icd10['nm_penyakit'];
         $diagnosa[] = $row;
       }

      $rows = $this->core->mysql('pemeriksaan_ralan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->toArray();
      $i = 1;
      $row['nama_petugas'] = '';
      $row['departemen_petugas'] = '';
      $result = [];
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
        $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
        $result[] = $row;
      }

      $result_ranap = [];

      $check_table = $this->core->mysql()->pdo()->query("SHOW TABLES LIKE 'pemeriksaan_ranap'");
      $check_table->execute();
      $check_table = $check_table->fetch();
      if($check_table) {
        $rows_ranap = $this->core->mysql('pemeriksaan_ranap')
          ->where('no_rawat', $_POST['no_rawat'])
          ->toArray();
        foreach ($rows_ranap as $row) {
          $row['nomor'] = $i++;
          $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
          $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
          $result_ranap[] = $row;
        }
      }

      echo $this->draw('soap.html', ['pemeriksaan' => $result, 'pemeriksaan_ranap' => $result_ranap, 'diagnosa' => $diagnosa, 'prosedur' => $prosedur, 'admin_mode' => $this->settings->get('settings.admin_mode')]);
      exit();
    }

    public function postSaveSOAP()
    {
      $_POST['nip'] = $this->core->getUserInfo('username', null, true);

      if(!$this->core->mysql('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->oneArray()) {
        $this->core->mysql('pemeriksaan_ralan')->save($_POST);
      } else {
        $this->core->mysql('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->save($_POST);
      }
      exit();
    }

    public function postHapusSOAP()
    {
      $this->core->mysql('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->delete();
      exit();
    }

    public function anyLayanan()
    {
      $layanan = $this->core->mysql('jns_perawatan')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['layanan'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('layanan.html', ['layanan' => $layanan]);
      exit();
    }

    public function anyBerkasDigital()
    {
      $berkas_digital = $this->core->mysql('berkas_digital_perawatan')->where('no_rawat', $_POST['no_rawat'])->toArray();
      echo $this->draw('berkasdigital.html', ['berkas_digital' => $berkas_digital]);
      exit();
    }

    public function postSaveBerkasDigital()
    {

      if(MULTI_APP) {

        $curl = curl_init();
        $filePath = $_FILES['file']['tmp_name'];

        curl_setopt_array($curl, array(
          CURLOPT_URL => str_replace('webapps','',WEBAPPS_URL).'api/berkasdigital',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array('file'=> new \CURLFILE($filePath),'token' => $this->settings->get('api.berkasdigital_key'), 'no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode']),
          CURLOPT_HTTPHEADER => array(),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $json = json_decode($response, true);
        if($json['status'] == 'Success') {
          echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$json['msg'].'" width="150" />';
        } else {
          echo 'Gagal menambahkan gambar';
        }

      } else {
        $dir    = $this->_uploads;
        $cntr   = 0;

        $image = $_FILES['file']['tmp_name'];
        $img = new \Systems\Lib\Image();
        $id = convertNorawat($_POST['no_rawat']);
        if ($img->load($image)) {
            $imgName = time().$cntr++;
            $imgPath = $dir.'/'.$id.'_'.$imgName.'.'.$img->getInfos('type');
            $lokasi_file = 'pages/upload/'.$id.'_'.$imgName.'.'.$img->getInfos('type');
            $img->save($imgPath);
            $query = $this->core->mysql('berkas_digital_perawatan')->save(['no_rawat' => $_POST['no_rawat'], 'kode' => $_POST['kode'], 'lokasi_file' => $lokasi_file]);
            if($query) {
              echo '<br><img src="'.WEBAPPS_URL.'/berkasrawat/'.$lokasi_file.'" width="150" />';
            }
        }

      }

      exit();

    }

    public function postProviderList()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->core->mysql('dokter')->like('nm_dokter', $key)->where('status', '1')->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["kd_dokter"].': '.$row["nm_dokter"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function postProviderList2()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->core->mysql('petugas')->like('nama', $key)->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["nip"].': '.$row["nama"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function postCekWaktu()
    {
      echo date('H:i:s');
      exit();
    }

    public function postMaxid()
    {
      $max_id = $this->core->mysql('reg_periksa')->select(['no_rawat' => 'ifnull(MAX(CONVERT(RIGHT(no_rawat,6),signed)),0)'])->where('tgl_registrasi', date('Y-m-d'))->oneArray();
      if(empty($max_id['no_rawat'])) {
        $max_id['no_rawat'] = '000000';
      }
      $_next_no_rawat = sprintf('%06s', ($max_id['no_rawat'] + 1));
      $next_no_rawat = date('Y/m/d').'/'.$_next_no_rawat;
      echo $next_no_rawat;
      exit();
    }

    public function postMaxAntrian()
    {
      $max_id = $this->core->mysql('reg_periksa')->select(['no_reg' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])->where('kd_poli', $_POST['kd_poli'])->where('tgl_registrasi', date('Y-m-d'))->desc('no_reg')->limit(1)->oneArray();
      if($this->settings->get('settings.dokter_ralan_per_dokter') == 'true') {
        $max_id = $this->core->mysql('reg_periksa')->select(['no_reg' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])->where('kd_poli', $_POST['kd_poli'])->where('kd_dokter', $_POST['kd_dokter'])->where('tgl_registrasi', date('Y-m-d'))->desc('no_reg')->limit(1)->oneArray();
      }
      if(empty($max_id['no_reg'])) {
        $max_id['no_reg'] = '000';
      }
      $_next_no_reg = sprintf('%03s', ($max_id['no_reg'] + 1));

      $date = date('Y-m-d');
      $tentukan_hari=date('D',strtotime(date('Y-m-d')));
      $day = array(
        'Sun' => 'AKHAD',
        'Mon' => 'SENIN',
        'Tue' => 'SELASA',
        'Wed' => 'RABU',
        'Thu' => 'KAMIS',
        'Fri' => 'JUMAT',
        'Sat' => 'SABTU'
      );
      $hari=$day[$tentukan_hari];

      $jadwal_dokter = $this->core->mysql('jadwal')->where('kd_poli', $_POST['kd_poli'])->where('kd_dokter', $_POST['kd_dokter'])->where('hari_kerja', $hari)->oneArray();
      $jadwal_poli = $this->core->mysql('jadwal')->where('kd_poli', $_POST['kd_poli'])->where('hari_kerja', $hari)->toArray();
      $kuota_poli = 0;
      foreach ($jadwal_poli as $row) {
        $kuota_poli += $row['kuota'];
      }
      if($this->settings->get('settings.dokter_ralan_per_dokter') == 'true' && $this->settings->get('settings.ceklimit') == 'true' && $_next_no_reg > $jadwal_dokter['kuota']) {
        $_next_no_reg = '888888';
      }
      if($this->settings->get('settings.dokter_ralan_per_dokter') == 'false' && $this->settings->get('settings.ceklimit') == 'true' && $_next_no_reg > $kuota_poli) {
        $_next_no_reg = '999999';
      }
      echo $_next_no_reg;
      exit();
    }

    public function getPasienInfo($field, $no_rkm_medis)
    {
        $row = $this->core->mysql('pasien')->where('no_rkm_medis', $no_rkm_medis)->oneArray();
        return $row[$field];
    }

    public function getRegPeriksaInfo($field, $no_rawat)
    {
        $row = $this->core->mysql('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
        return $row[$field];
    }

    public function setNoRawat()
    {
        $date = date('Y-m-d');
        $last_no_rawat = $this->core->mysql()->pdo()->prepare("SELECT ifnull(MAX(CONVERT(RIGHT(no_rawat,6),signed)),0) FROM reg_periksa WHERE tgl_registrasi = '$date'");
        $last_no_rawat->execute();
        $last_no_rawat = $last_no_rawat->fetch();
        if(empty($last_no_rawat[0])) {
          $last_no_rawat[0] = '000000';
        }
        $next_no_rawat = sprintf('%06s', ($last_no_rawat[0] + 1));
        $next_no_rawat = date('Y/m/d').'/'.$next_no_rawat;

        return $next_no_rawat;
    }

    public function postCetak()
    {
      $this->core->mysql()->pdo()->exec("DELETE FROM `mlite_temporary`");
      $cari = $_POST['cari'];
      $tgl_awal = $_POST['tgl_awal'];
      $tgl_akhir = $_POST['tgl_akhir'];
      $igd = $this->settings->get('settings.igd');
      $this->core->mysql()->pdo()->exec("INSERT INTO `mlite_temporary` (
        `temp1`,`temp2`,`temp3`,`temp4`,`temp5`,`temp6`,`temp7`,`temp8`,`temp9`,`temp10`,`temp11`,`temp12`,`temp13`,`temp14`,`temp15`,`temp16`,`temp17`,`temp18`,`temp19`
      )
      SELECT *
      FROM `reg_periksa`
      WHERE `kd_poli` <> '$igd'
      AND `tgl_registrasi` BETWEEN '$tgl_awal' AND '$tgl_akhir'
      ");
      exit();
    }

    public function getCetakPdf()
    {
      $tmp = $this->core->mysql('mlite_temporary')->toArray();
      $logo = $this->settings->get('settings.logo');

      $pdf = new PDF_MC_Table('L','mm','Legal');
      $pdf->AddPage();
      $pdf->SetAutoPageBreak(true, 10);
      $pdf->SetTopMargin(10);
      $pdf->SetLeftMargin(10);
      $pdf->SetRightMargin(10);

      $pdf->Image('../'.$logo, 10, 8, '18', '18', 'png');
      $pdf->SetFont('Arial', '', 24);
      $pdf->Text(30, 16, $this->settings->get('settings.nama_instansi'));
      $pdf->SetFont('Arial', '', 10);
      $pdf->Text(30, 21, $this->settings->get('settings.alamat').' - '.$this->settings->get('settings.kota'));
      $pdf->Text(30, 25, $this->settings->get('settings.nomor_telepon').' - '.$this->settings->get('settings.email'));
      $pdf->Line(10, 30, 345, 30);
      $pdf->Line(10, 31, 345, 31);
      $pdf->SetFont('Arial', 'B', 13);
      $pdf->Text(10, 40, 'DATA PENDAFTARAN POLIKLINIK');
      $pdf->Ln(34);
      $pdf->SetFont('Arial', 'B', 11);
      $pdf->SetWidths(array(25,35,20,80,25,50,50,50));
      $pdf->Row(array('Tanggal','No. Rawat','No. Reg','Nama Pasien','No. RM','Poliklinik','Dokter','Penjamin'));
      $pdf->SetFont('Arial', '', 10);
      foreach ($tmp as $hasil) {
        $poliklinik = $this->core->mysql('poliklinik')->where('kd_poli', $hasil['temp7'])->oneArray();
        $dokter = $this->core->mysql('dokter')->where('kd_dokter', $hasil['temp5'])->oneArray();
        $penjab = $this->core->mysql('penjab')->where('kd_pj', $hasil['temp15'])->oneArray();
        $pdf->Row(array($hasil['temp3'],$hasil['temp2'],$hasil['temp1'],$this->core->getPasienInfo('nm_pasien', $hasil['temp6']),$hasil['temp6'],$poliklinik['nm_poli'],$dokter['nm_dokter'],$penjab['png_jawab']));
      }
      $pdf->Output('cetak'.date('Y-m-d').'.pdf','I');
    }

    public function postObatKronis()
    {
      if (isset($_POST['no_rawat']) && $_POST['no_rawat'] !='') {
        $reg_periksa = $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
        $bridging_sep = $this->core->mysql('bridging_sep')->where('no_rawat', $_POST['no_rawat'])->oneArray();
        if(!$bridging_sep) {
          $bridging_sep['no_sep'] = '';
        }
        $this->core->mysql('mlite_veronisa')->save([
          'id' => NULL,
          'tanggal' => date('Y-m-d'),
          'no_rkm_medis' => $reg_periksa['no_rkm_medis'],
          'no_rawat' => $_POST['no_rawat'],
          'tgl_registrasi' => $reg_periksa['tgl_registrasi'],
          'nosep' => $bridging_sep['no_sep'],
          'status' => 'Belum',
          'username' => $this->core->getUserInfo('username', null, true)
        ]);
      }
      exit();
    }

    public function getOdontogram($no_rkm_medis)
    {

      $odt['18'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_18')->desc('tgl_input')->limit(1)->oneArray();
      $odt['17'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_17')->desc('tgl_input')->limit(1)->oneArray();
      $odt['16'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_16')->desc('tgl_input')->limit(1)->oneArray();
      $odt['15'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_15')->desc('tgl_input')->limit(1)->oneArray();
      $odt['14'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_14')->desc('tgl_input')->limit(1)->oneArray();
      $odt['13'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_13')->desc('tgl_input')->limit(1)->oneArray();
      $odt['12'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_12')->desc('tgl_input')->limit(1)->oneArray();
      $odt['11'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_11')->desc('tgl_input')->limit(1)->oneArray();
      $odt['21'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_21')->desc('tgl_input')->limit(1)->oneArray();
      $odt['22'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_22')->desc('tgl_input')->limit(1)->oneArray();
      $odt['23'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_23')->desc('tgl_input')->limit(1)->oneArray();
      $odt['24'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_24')->desc('tgl_input')->limit(1)->oneArray();
      $odt['25'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_25')->desc('tgl_input')->limit(1)->oneArray();
      $odt['26'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_26')->desc('tgl_input')->limit(1)->oneArray();
      $odt['27'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_27')->desc('tgl_input')->limit(1)->oneArray();
      $odt['28'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_28')->desc('tgl_input')->limit(1)->oneArray();
      $odt['38'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_38')->desc('tgl_input')->limit(1)->oneArray();
      $odt['37'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_37')->desc('tgl_input')->limit(1)->oneArray();
      $odt['36'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_36')->desc('tgl_input')->limit(1)->oneArray();
      $odt['35'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_35')->desc('tgl_input')->limit(1)->oneArray();
      $odt['34'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_34')->desc('tgl_input')->limit(1)->oneArray();
      $odt['33'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_33')->desc('tgl_input')->limit(1)->oneArray();
      $odt['32'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_32')->desc('tgl_input')->limit(1)->oneArray();
      $odt['31'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_31')->desc('tgl_input')->limit(1)->oneArray();
      $odt['41'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_41')->desc('tgl_input')->limit(1)->oneArray();
      $odt['42'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_42')->desc('tgl_input')->limit(1)->oneArray();
      $odt['43'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_43')->desc('tgl_input')->limit(1)->oneArray();
      $odt['44'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_44')->desc('tgl_input')->limit(1)->oneArray();
      $odt['45'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_45')->desc('tgl_input')->limit(1)->oneArray();
      $odt['46'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_46')->desc('tgl_input')->limit(1)->oneArray();
      $odt['47'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_47')->desc('tgl_input')->limit(1)->oneArray();
      $odt['48'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_48')->desc('tgl_input')->limit(1)->oneArray();
      $odt['55'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_55')->desc('tgl_input')->limit(1)->oneArray();
      $odt['54'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_54')->desc('tgl_input')->limit(1)->oneArray();
      $odt['53'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_53')->desc('tgl_input')->limit(1)->oneArray();
      $odt['52'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_52')->desc('tgl_input')->limit(1)->oneArray();
      $odt['51'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_51')->desc('tgl_input')->limit(1)->oneArray();
      $odt['61'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_61')->desc('tgl_input')->limit(1)->oneArray();
      $odt['62'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_62')->desc('tgl_input')->limit(1)->oneArray();
      $odt['63'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_63')->desc('tgl_input')->limit(1)->oneArray();
      $odt['64'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_64')->desc('tgl_input')->limit(1)->oneArray();
      $odt['65'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_65')->desc('tgl_input')->limit(1)->oneArray();
      $odt['75'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_75')->desc('tgl_input')->limit(1)->oneArray();
      $odt['74'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_74')->desc('tgl_input')->limit(1)->oneArray();
      $odt['73'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_73')->desc('tgl_input')->limit(1)->oneArray();
      $odt['72'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_72')->desc('tgl_input')->limit(1)->oneArray();
      $odt['71'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_71')->desc('tgl_input')->limit(1)->oneArray();
      $odt['81'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_81')->desc('tgl_input')->limit(1)->oneArray();
      $odt['82'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_82')->desc('tgl_input')->limit(1)->oneArray();
      $odt['83'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_83')->desc('tgl_input')->limit(1)->oneArray();
      $odt['84'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_84')->desc('tgl_input')->limit(1)->oneArray();
      $odt['85'] = $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->where('pemeriksaan', 'gg_85')->desc('tgl_input')->limit(1)->oneArray();

      echo $this->draw('odontogram.html', [
        'odontogram' => $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->toArray(), 
        'ohis' => $this->core->mysql('mlite_ohis')->where('no_rkm_medis', $no_rkm_medis)->toArray(), 
        'odt' => $odt
      ]);
      exit();
    }

    public function getOdontogramTampil($no_rkm_medis)
    {
      echo $this->draw('odontogram.tampil.html', ['odontogram' => $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->toArray()]);
      exit();
    }

    public function postOdontogramSave()
    {
      $_POST['id_user']	= $this->core->getUserInfo('id');
      $_POST['tgl_input'] = date('Y-m-d');
      $query = $this->core->mysql('mlite_odontogram')->save($_POST);
      exit();
    }

    public function postOdontogramDelete()
    {
      $_POST['id_user']	= $this->core->getUserInfo('id');
      $query = $this->core->mysql('mlite_odontogram')
      ->where('no_rkm_medis', $_POST['no_rkm_medis'])
      ->where('pemeriksaan', $_POST['pemeriksaan'])
      ->where('kondisi', $_POST['kondisi'])
      ->where('catatan', $_POST['catatan'])
      ->where('tgl_input', $_POST['tgl_input'])
      ->where('id_user', $_POST['id_user'])
      ->delete();
      exit();
    }

    public function getOhisTampil($no_rkm_medis)
    {
      echo $this->draw('ohis.tampil.html', ['ohis' => $this->core->mysql('mlite_ohis')->where('no_rkm_medis', $no_rkm_medis)->toArray()]);
      exit();
    }

    public function postOhisSave()
    {
      $_POST['id_user']	= $this->core->getUserInfo('id');
      $_POST['tgl_input'] = date('Y-m-d');
      $_POST['debris'] = ($_POST['d_16']+$_POST['d_11']+$_POST['d_26']+$_POST['d_36']+$_POST['d_31']+$_POST['d_46'])/6;
      $_POST['debris'] = ceil($_POST['debris']*100)/100;
      $_POST['calculus'] = ($_POST['c_16']+$_POST['c_11']+$_POST['c_26']+$_POST['c_36']+$_POST['c_31']+$_POST['c_46'])/6;
      $_POST['calculus'] = ceil($_POST['calculus']*100)/100;
      $_POST['nilai'] = $_POST['debris']+$_POST['calculus'];
      if($_POST['nilai'] >= '0,0' && $_POST['nilai'] <= '1,2') {
        $_POST['kriteria'] = 'Baik';
      } elseif($_POST['nilai'] >= '1,3' && $_POST['nilai'] <= '3,0') {
        $_POST['kriteria'] = 'Sedang';
      } elseif($_POST['nilai'] >= '1,3' && $_POST['nilai'] <= '3,0') {
        $_POST['kriteria'] = 'Buruk';
      } else {
        $_POST['kriteria'] = '';
      }
      $query = $this->core->mysql('mlite_ohis')->save($_POST);
      exit();
    }

    public function postOhisDelete()
    {
      $_POST['id_user']	= $this->core->getUserInfo('id');
      $query = $this->core->mysql('mlite_ohis')
      ->where('no_rkm_medis', $_POST['no_rkm_medis'])
      ->where('tgl_input', $_POST['tgl_input'])
      ->where('id_user', $_POST['id_user'])
      ->delete();
      exit();
    }

    public function getSepDetail($no_sep)
    {
      $sep = $this->core->mysql('bridging_sep')->where('no_sep', $no_sep)->oneArray();
      $this->tpl->set('sep', $this->tpl->noParse_array(htmlspecialchars_array($sep)));

      $potensi_prb = $this->core->mysql('bpjs_prb')->where('no_sep', $no_sep)->oneArray();
      $data_sep['potensi_prb'] = $potensi_prb['prb'];
      echo $this->draw('sep.detail.html', ['data_sep' => $data_sep]);
      exit();
    }

    public function getSuratRujukan($no_rawat)
    {
        $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', revertNoRawat($no_rawat));
        $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($no_rawat));
        $pasien = $this->core->mysql('pasien')
          ->join('kelurahan', 'kelurahan.kd_kel=pasien.kd_kel')
          ->join('kecamatan', 'kecamatan.kd_kec=pasien.kd_kec')
          ->join('kabupaten', 'kabupaten.kd_kab=pasien.kd_kab')
          ->join('propinsi', 'propinsi.kd_prop=pasien.kd_prop')
          ->where('no_rkm_medis', $no_rkm_medis)
          ->oneArray();
        $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $this->tpl->set('pasien', $this->tpl->noParse_array(htmlspecialchars_array($pasien)));
        $this->tpl->set('nm_dokter', $nm_dokter);
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($this->settings('settings'))));
        echo $this->tpl->draw(MODULES.'/rawat_jalan/view/admin/surat.rujukan.html', true);
        exit();
    }

    public function getSuratSehat($no_rawat)
    {
        $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', revertNoRawat($no_rawat));
        $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($no_rawat));
        $pasien = $this->core->mysql('pasien')
          ->join('kelurahan', 'kelurahan.kd_kel=pasien.kd_kel')
          ->join('kecamatan', 'kecamatan.kd_kec=pasien.kd_kec')
          ->join('kabupaten', 'kabupaten.kd_kab=pasien.kd_kab')
          ->join('propinsi', 'propinsi.kd_prop=pasien.kd_prop')
          ->where('no_rkm_medis', $no_rkm_medis)
          ->oneArray();
        $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $this->tpl->set('pasien', $this->tpl->noParse_array(htmlspecialchars_array($pasien)));
        $this->tpl->set('nm_dokter', $nm_dokter);
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($this->settings('settings'))));
        echo $this->tpl->draw(MODULES.'/rawat_jalan/view/admin/surat.sehat.html', true);
        exit();
    }

    public function getSuratSakit($no_rawat)
    {
        $kd_dokter = $this->core->getRegPeriksaInfo('kd_dokter', revertNoRawat($no_rawat));
        $no_rkm_medis = $this->core->getRegPeriksaInfo('no_rkm_medis', revertNoRawat($no_rawat));
        $pasien = $this->core->mysql('pasien')
          ->join('kelurahan', 'kelurahan.kd_kel=pasien.kd_kel')
          ->join('kecamatan', 'kecamatan.kd_kec=pasien.kd_kec')
          ->join('kabupaten', 'kabupaten.kd_kab=pasien.kd_kab')
          ->join('propinsi', 'propinsi.kd_prop=pasien.kd_prop')
          ->where('no_rkm_medis', $no_rkm_medis)
          ->oneArray();
        $nm_dokter = $this->core->getPegawaiInfo('nama', $kd_dokter);
        $this->tpl->set('pasien', $this->tpl->noParse_array(htmlspecialchars_array($pasien)));
        $this->tpl->set('nm_dokter', $nm_dokter);
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($this->settings('settings'))));
        echo $this->tpl->draw(MODULES.'/rawat_jalan/view/admin/surat.sakit.html', true);
        exit();
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        $cek_pegawai = $this->core->mysql('pegawai')->where('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']))->oneArray();
        $cek_role = '';
        if($cek_pegawai) {
          $cek_role = $this->core->getPegawaiInfo('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']));
        }
        echo $this->draw(MODULES.'/rawat_jalan/js/admin/rawat_jalan.js', ['cek_role' => $cek_role]);
        exit();
    }

    private function _addHeaderFiles()
    {
        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
        $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));
        $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
        $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
        $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
        $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
        $this->core->addJS(url([ADMIN, 'rawat_jalan', 'javascript']), 'footer');
    }

    protected function data_icd($table)
    {
        return new DB_ICD($table);
    }

}
