<?php

namespace Plugins\Anjungan;

use Systems\SiteModule;
use Systems\Lib\BpjsService;
use Systems\Lib\QRCode;

class Site extends SiteModule
{

    protected $consid;
    protected $secretkey;
    protected $user_key;
    protected $api_url;

    public function init()
    {
      $this->consid = $this->settings->get('settings.BpjsConsID');
      $this->secretkey = $this->settings->get('settings.BpjsSecretKey');
      $this->user_key = $this->settings->get('settings.BpjsUserKey');
      $this->api_url = $this->settings->get('settings.BpjsApiUrl');
    }

    public function routes()
    {
        $this->route('anjungan', 'getIndex');
        $this->route('anjungan/pasien', 'getDisplayAPM');
        $this->route('anjungan/loket', 'getDisplayAntrianLoket');
        $this->route('anjungan/loket2', 'getDisplayAntrianLoket2');
        $this->route('anjungan/poli', 'getDisplayAntrianPoli');

        /* Sumbangan Mbak Kiki Sagira RS Bhayangkara Makassar */
        $this->route('anjungan/display/poli/(:str)', 'getDisplayAntrianPoliSatu');
    		$this->route('anjungan/display/poli/(:str)/(:str)', 'getDisplayAntrianPoliDua');
    		$this->route('anjungan/display/poli/(:str)/(:str)/(:str)', 'getDisplayAntrianPoliTiga');
    		$this->route('anjungan/poli/(:str)/(:str)/(:str)', 'getDisplayAntrianPoliKodex');
        /* End Sumbangan Mbak Kiki Sagira RS Bhayangkara Makassar */

        $this->route('anjungan/poli/(:str)', 'getDisplayAntrianPoliKode');
        $this->route('anjungan/poli/(:str)/(:str)', 'getDisplayAntrianPoliKode');
        $this->route('anjungan/display/poli/(:str)', 'getDisplayAntrianPoliDisplay');
        $this->route('anjungan/display/poli/(:str)/(:str)', 'getDisplayAntrianPoliDisplay');
        $this->route('anjungan/laboratorium', 'getDisplayAntrianLaboratorium');
        $this->route('anjungan/apotek', 'getDisplayAntrianApotek');
        $this->route('anjungan/apotek/ambilantrian', 'getDisplayAntrianApotekAmbil');
        $this->route('anjungan/ajax', 'getAjax');
        $this->route('anjungan/panggilantrian', 'getPanggilAntrian');
        $this->route('anjungan/panggilselesai', 'getPanggilSelesai');
        $this->route('anjungan/simpannorm', 'getSimpanNoRM');
        $this->route('anjungan/setpanggil', 'getSetPanggil');
        $this->route('anjungan/presensi', 'getPresensi');
        $this->route('anjungan/presensi/upload', 'getUpload');
        $this->route('anjungan/bed', 'getDisplayBed');
        $this->route('anjungan/sep', 'getSepMandiri');
        $this->route('anjungan/sep/cek', 'getSepMandiriCek');
        $this->route('anjungan/sep/(:int)/(:int)', 'getSepMandiriNokaNorm');
        $this->route('anjungan/sep/bikin/(:str)/(:int)/(:str)', 'getSepMandiriBikin');
        $this->route('anjungan/sep/savesep', 'postSaveSep');
        $this->route('anjungan/sep/cetaksep/(:str)', 'getCetakSEP');
        $this->route('anjungan/checkin/(:str)', 'getDisplayCheckin');
        $this->route('anjungan/daftar/(:str)', 'getDaftarBPJS');
        $this->route('anjungan/jadwaloperasi', 'getDisplayJadwalOperasi');
        //$this->route('anjungan/daftar/baru/(:str)', 'getDaftarBPJS');
    }

    public function getIndex()
    {
        echo $this->draw('index.html', ['test' => 'Opo iki']);
        exit();
    }

    public function getDisplayAPM()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $poliklinik = $this->core->mysql('poliklinik')->toArray();
        $carabayar = str_replace(",","','", $this->settings->get('anjungan.carabayar'));
        $penjab = $this->core->mysql()->pdo()->prepare("SELECT * FROM penjab WHERE kd_pj IN ('$carabayar')");
        $penjab->execute();
        $penjab = $penjab->fetchAll(\PDO::FETCH_ASSOC);;

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'running_text' => $this->settings->get('anjungan.text_anjungan'),
          'poliklinik' => $poliklinik,
          'penjab' => $penjab
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function getAksiLoket()
    {
      switch (isset($_GET['act'])) {
        case 'reseta':
          if (!isset($_GET['antrian'])){
              $this->core->db('mlite_settings')->where('module','anjungan')->where('field','no_antrian_loket')->update('value',0);
              $this->core->db('mlite_settings')->where('module','anjungan')->where('field','konter_antrian_loket')->update('value',0);
          }
          else {
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','no_antrian_loket')->update('value',$_GET['antrian']);
              $this->core->db('mlite_settings')->where('module','anjungan')->where('field','konter_antrian_loket')->update('value',$_GET['loket']);
          }
          break;

        case 'resetb':
          if (!isset($_GET['antrian'])){
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','no_antrian_cs')->update('value',0);
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','konter_antrian_cs')->update('value',0);
          }
          else {
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','no_antrian_cs')->update('value',$_GET['antrian']);
              $this->core->db('mlite_settings')->where('module','anjungan')->where('field','konter_antrian_cs')->update('value',$_GET['loket']);
          }
          break;

        case 'resetc':
          if (!isset($_GET['antrian'])){
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','no_antrian_igd')->update('value',0);
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','konter_antrian_igd')->update('value',0);
          }
          else {
            $this->core->db('mlite_settings')->where('module','anjungan')->where('field','no_antrian_igd')->update('value',$_GET['antrian']);
              $this->core->db('mlite_settings')->where('module','anjungan')->where('field','konter_antrian_igd')->update('value',$_GET['loket']);
          }
          break;

        case 'getantriana':
          $tcounter = $this->settings->get('anjungan.no_antrian_loket');
          $_tcounter = $tcounter + 1;

          if (isset($_GET['loket'])) {
            $this->core->db('mlite_antrian_loket')
            ->where('noantrian',$_tcounter)
            ->where('type','Loket')
            ->where('postdate', date('Y-m-d'))
            ->update(['end_time' => date('H:i:s'), 'loket' => $_GET['loket'], 'status' => 1 ]);

            $this->core->db('mlite_settings')
            ->where('module','anjungan')
            ->where('field','no_antrian_loket')
            ->update('value',$_tcounter);

            $this->core->db('mlite_settings')
            ->where('module','anjungan')
            ->where('field','konter_antrian_loket')
            ->update('value',$_GET['loket']);
          }
          echo 'A'.$tcounter;
          break;

        case 'getaudioa' :
          $tcounter = $this->settings->get('anjungan.no_antrian_loket');
          $panjang = strlen($tcounter);

          for ($i = 0; $i < $panjang; $i++) {
            echo '<audio id="suarabela' . $i . '" src="'.url().'/plugins/anjungan/suara/' . substr($tcounter, $i, 1) . '.wav" ></audio>';
          }

          $this->core->db('mlite_antrian_loket')
            ->where('noantrian',$tcounter)
            ->where('type','Loket')
            ->where('postdate', date('Y-m-d'))
            ->update('status',2);

          echo '<audio id="a" src="'.url().'/plugins/anjungan/suara/a.wav"  ></audio>';
          break;

          case 'getantrianb':
            $tcounter = $this->settings->get('anjungan.no_antrian_cs');
            $_tcounter = $tcounter + 1;

            if (isset($_GET['loket'])) {
              $this->core->db('mlite_antrian_loket')
              ->where('noantrian',$_tcounter)
              ->where('type','CS')
              ->where('postdate', date('Y-m-d'))
              ->update(['end_time' => date('H:i:s'), 'loket' => $_GET['loket'], 'status' => 1 ]);

              $this->core->db('mlite_settings')
              ->where('module','anjungan')
              ->where('field','no_antrian_cs')
              ->update('value',$_tcounter);

              $this->core->db('mlite_settings')
              ->where('module','anjungan')
              ->where('field','konter_antrian_cs')
              ->update('value',$_GET['loket']);
            }
            echo 'B'.$tcounter;
            break;

          case 'getaudiob' :
            $tcounter = $this->settings->get('anjungan.no_antrian_cs');
            $panjang = strlen($tcounter);

            for ($i = 0; $i < $panjang; $i++) {
              echo '<audio id="suarabelb' . $i . '" src="'.url().'/plugins/anjungan/suara/' . substr($tcounter, $i, 1) . '.wav" ></audio>';
            }

            $this->core->db('mlite_antrian_loket')
              ->where('noantrian',$tcounter)
              ->where('type','CS')
              ->where('postdate', date('Y-m-d'))
              ->update('status',2);

            echo '<audio id="b" src="'.url().'/plugins/anjungan/suara/b.wav"  ></audio>';
            break;

            case 'getantrianc':
              $tcounter = $this->settings->get('anjungan.no_antrian_igd');
              $_tcounter = $tcounter + 1;

              if (isset($_GET['loket'])) {
                $this->core->db('mlite_antrian_loket')
                ->where('noantrian',$_tcounter)
                ->where('type','IGD')
                ->where('postdate', date('Y-m-d'))
                ->update(['end_time' => date('H:i:s'), 'loket' => $_GET['loket'], 'status' => 1 ]);

                $this->core->db('mlite_settings')
                ->where('module','anjungan')
                ->where('field','no_antrian_igd')
                ->update('value',$_tcounter);

                $this->core->db('mlite_settings')
                ->where('module','anjungan')
                ->where('field','konter_antrian_igd')
                ->update('value',$_GET['loket']);
              }
              echo 'C'.$tcounter;
              break;

            case 'getaudioc' :
              $tcounter = $this->settings->get('anjungan.no_antrian_igd');
              $panjang = strlen($tcounter);

              for ($i = 0; $i < $panjang; $i++) {
                echo '<audio id="suarabelc' . $i . '" src="'.url().'/plugins/anjungan/suara/' . substr($tcounter, $i, 1) . '.wav" ></audio>';
              }

              $this->core->db('mlite_antrian_loket')
                ->where('noantrian',$tcounter)
                ->where('type','IGD')
                ->where('postdate', date('Y-m-d'))
                ->update('status',2);

              echo '<audio id="c" src="'.url().'/plugins/anjungan/suara/c.wav"  ></audio>';
              break;

            case 'getallantriana' :
              $max = $this->core->db('mlite_antrian_loket')->where('type','Loket')->where('postdate', date('Y-m-d'))->desc('noantrian')->oneArray();

              $allantrian = 0;

              if(!empty($max)){
                $allantrian = $max['noantrian'];
              }

              echo $allantrian;
              break;

            case 'getallantrianb' :
              $max = $this->core->db('mlite_antrian_loket')->where('type','CS')->where('postdate', date('Y-m-d'))->desc('noantrian')->oneArray();

              $allantrian = 0;

              if(!empty($max)){
                $allantrian = $max['noantrian'];
              }

              echo $allantrian;
              break;

            case 'getallantrianc' :
              $max = $this->core->db('mlite_antrian_loket')->where('type','IGD')->where('postdate', date('Y-m-d'))->desc('noantrian')->oneArray();

              $allantrian = 0;

              if(!empty($max)){
                $allantrian = $max['noantrian'];
              }

              echo $allantrian;
              break;

        default:
          # code...
          break;
      }

      exit();
    }

    private function _getPenjab($kd_pj = null)
    {
        $result = [];
        $rows = $this->core->mysql('penjab')->where('status', '1')->toArray();

        if (!$kd_pj) {
            $kd_pjArray = [];
        } else {
            $kd_pjArray = explode(',', $kd_pj);
        }

        foreach ($rows as $row) {
            if (empty($kd_pjArray)) {
                $attr = '';
            } else {
                if (in_array($row['kd_pj'], $kd_pjArray)) {
                    $attr = 'selected';
                } else {
                    $attr = '';
                }
            }
            $result[] = ['kd_pj' => $row['kd_pj'], 'png_jawab' => $row['png_jawab'], 'attr' => $attr];
        }
        return $result;
    }

    public function getDisplayAntrianPoli()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $display = $this->_resultDisplayAntrianPoli();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'running_text' => $this->settings->get('anjungan.text_poli'),
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayAntrianPoli()
    {
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

        $poliklinik = str_replace(",","','", $this->settings->get('anjungan.display_poli'));
        $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari'  AND a.kd_poli IN ('$poliklinik')");
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);;

        $result = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['dalam_pemeriksaan'] = $this->core->mysql('reg_periksa')
                  ->select('no_reg')
                  ->select('nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('tgl_registrasi', $date)
                  ->where('stts', 'Berkas Diterima')
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->limit(1)
                  ->oneArray();
                $row['dalam_antrian'] = $this->core->mysql('reg_periksa')
                  ->select(['jumlah' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['sudah_dilayani'] = $this->core->mysql('reg_periksa')
                  ->select(['count' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->where('reg_periksa.stts', 'Sudah')
                  ->oneArray();
                $row['sudah_dilayani']['jumlah'] = 0;
                if(!empty($row['sudah_dilayani'])) {
                  $row['sudah_dilayani']['jumlah'] = $row['sudah_dilayani']['count'];
                }
                $row['selanjutnya'] = $this->core->mysql('reg_periksa')
                  ->select('reg_periksa.no_reg')
                  //->select(['no_urut_reg' => 'ifnull(MAX(CONVERT(RIGHT(reg_periksa.no_reg,3),signed)),0)'])
                  ->select('pasien.nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('reg_periksa.tgl_registrasi', $date)
                  ->where('reg_periksa.stts', 'Belum')
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->asc('reg_periksa.no_reg')
                  ->toArray();
                $row['get_no_reg'] = $this->core->mysql('reg_periksa')
                  ->select(['max' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])
                  ->where('tgl_registrasi', $date)
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['diff'] = (strtotime($row['jam_selesai'])-strtotime($row['jam_mulai']))/60;
                $row['interval'] = 0;
                if($row['diff'] == 0) {
                  $row['interval'] = round($row['diff']/$row['get_no_reg']['max']);
                }
                if($row['interval'] > 10){
                  $interval = 10;
                } else {
                  $interval = $row['interval'];
                }
                foreach ($row['selanjutnya'] as $value) {
                  //$minutes = $value['no_reg'] * $interval;
                  //$row['jam_mulai'] = date('H:i',strtotime('+10 minutes',strtotime($row['jam_mulai'])));
                }

                $result[] = $row;
            }
        }

        return $result;
    }

    public function getDisplayAntrianPoliKode()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $slug = parseURL();
        $vidio = $this->settings->get('anjungan.vidio');
        $_GET['vid'] = '';
        if(isset($_GET['vid']) && $_GET['vid'] !='') {
          $vidio = $_GET['vid'];
        }

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

        $running_text = $this->settings->get('anjungan.text_poli');
        $jadwal = $this->core->mysql('jadwal')->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')->where('hari_kerja', $hari)->toArray();
        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.kode.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'vidio' => $vidio,
          'running_text' => $running_text,
          'jadwal' => $jadwal,
          'slug' => $slug
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    /* Sumbangan Mbak Kiki Sagira RS Bhayangkara Makassar */
    public function getDisplayAntrianPoliKodex()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $slug = parseURL();
        $vidio = $this->settings->get('anjungan.vidio');
        $_GET['vid'] = '';
        if(isset($_GET['vid']) && $_GET['vid'] !='') {
          $vidio = $_GET['vid'];
        }

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

        $running_text = $this->settings->get('anjungan.text_poli');
        $jadwal = $this->core->mysql('jadwal')->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')->where('hari_kerja', $hari)->toArray();
        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.tvb.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'vidio' => $vidio,
          'running_text' => $running_text,
          'jadwal' => $jadwal,
          'slug' => $slug
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function getDisplayAntrianPoliSatu()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $display = $this->_resultDisplayAntrianPoliKodeSatu();
        $slug = parseURL();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.displaytv.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'vidio' => $this->settings->get('anjungan.vidio'),
          'running_text' => $this->settings->get('anjungan.text_poli'),
          'slug' => $slug,
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayAntrianPoliKodeSatu()
    {
        $slug = parseURL();

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

        $poliklinik = $slug[3];
        $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari' AND a.kd_poli = '$poliklinik'");
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);;

        $result = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['dalam_pemeriksaan'] = $this->core->mysql('reg_periksa')
                  ->select('no_reg')
                  ->select('nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('tgl_registrasi', $date)
                  ->where('stts', 'Berkas Diterima')
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->limit(1)
                  ->oneArray();
                $row['dalam_antrian'] = $this->core->mysql('reg_periksa')
                  ->select(['jumlah' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['sudah_dilayani'] = $this->core->mysql('reg_periksa')
                  ->select(['count' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->where('reg_periksa.stts', 'Sudah')
                  ->oneArray();
                $row['sudah_dilayani']['jumlah'] = 0;
                if(!empty($row['sudah_dilayani'])) {
                  $row['sudah_dilayani']['jumlah'] = $row['sudah_dilayani']['count'];
                }
                $row['selanjutnya'] = $this->core->mysql('reg_periksa')
                  ->select('reg_periksa.no_reg')
                  //->select(['no_urut_reg' => 'ifnull(MAX(CONVERT(RIGHT(reg_periksa.no_reg,3),signed)),0)'])
                  ->select('pasien.nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('reg_periksa.tgl_registrasi', $date)
                  ->where('reg_periksa.stts', 'Belum')
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->asc('reg_periksa.no_reg')
                  ->toArray();
                $row['get_no_reg'] = $this->core->mysql('reg_periksa')
                  ->select(['max' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])
                  ->where('tgl_registrasi', $date)
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['diff'] = (strtotime($row['jam_selesai'])-strtotime($row['jam_mulai']))/60;
                $row['interval'] = 0;
                if($row['diff'] == 0) {
                  $row['interval'] = round($row['diff']/$row['get_no_reg']['max']);
                }
                if($row['interval'] > 10){
                  $interval = 10;
                } else {
                  $interval = $row['interval'];
                }
                foreach ($row['selanjutnya'] as $value) {
                  //$minutes = $value['no_reg'] * $interval;
                  //$row['jam_mulai'] = date('H:i',strtotime('+10 minutes',strtotime($row['jam_mulai'])));
                }

                $result[] = $row;
            }
        }

        return $result;
    }

    public function getDisplayAntrianPoliDua()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $display = $this->_resultDisplayAntrianPoliKodeDua();
        $slug = parseURL();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.displaytv.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'vidio' => $this->settings->get('anjungan.vidio'),
          'running_text' => $this->settings->get('anjungan.text_poli'),
          'slug' => $slug,
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayAntrianPoliKodeDua()
    {
        $slug = parseURL();

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

        $poliklinik = $slug[4];
        $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari' AND a.kd_poli = '$poliklinik'");
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);;

        $result = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['dalam_pemeriksaan'] = $this->core->mysql('reg_periksa')
                  ->select('no_reg')
                  ->select('nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('tgl_registrasi', $date)
                  ->where('stts', 'Berkas Diterima')
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->limit(1)
                  ->oneArray();
                $row['dalam_antrian'] = $this->core->mysql('reg_periksa')
                  ->select(['jumlah' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['sudah_dilayani'] = $this->core->mysql('reg_periksa')
                  ->select(['count' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->where('reg_periksa.stts', 'Sudah')
                  ->oneArray();
                $row['sudah_dilayani']['jumlah'] = 0;
                if(!empty($row['sudah_dilayani'])) {
                  $row['sudah_dilayani']['jumlah'] = $row['sudah_dilayani']['count'];
                }
                $row['selanjutnya'] = $this->core->mysql('reg_periksa')
                  ->select('reg_periksa.no_reg')
                  //->select(['no_urut_reg' => 'ifnull(MAX(CONVERT(RIGHT(reg_periksa.no_reg,3),signed)),0)'])
                  ->select('pasien.nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('reg_periksa.tgl_registrasi', $date)
                  ->where('reg_periksa.stts', 'Belum')
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->asc('reg_periksa.no_reg')
                  ->toArray();
                $row['get_no_reg'] = $this->core->mysql('reg_periksa')
                  ->select(['max' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])
                  ->where('tgl_registrasi', $date)
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['diff'] = (strtotime($row['jam_selesai'])-strtotime($row['jam_mulai']))/60;
                $row['interval'] = 0;
                if($row['diff'] == 0) {
                  $row['interval'] = round($row['diff']/$row['get_no_reg']['max']);
                }
                if($row['interval'] > 10){
                  $interval = 10;
                } else {
                  $interval = $row['interval'];
                }
                foreach ($row['selanjutnya'] as $value) {
                  //$minutes = $value['no_reg'] * $interval;
                  //$row['jam_mulai'] = date('H:i',strtotime('+10 minutes',strtotime($row['jam_mulai'])));
                }

                $result[] = $row;
            }
        }

        return $result;
    }

    public function getDisplayAntrianPoliTiga()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $display = $this->_resultDisplayAntrianPoliKodeTiga();
        $slug = parseURL();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.displaytv.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'vidio' => $this->settings->get('anjungan.vidio'),
          'running_text' => $this->settings->get('anjungan.text_poli'),
          'slug' => $slug,
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayAntrianPoliKodeTiga()
    {
        $slug = parseURL();

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

        $poliklinik = $slug[5];
        $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari' AND a.kd_poli = '$poliklinik'");
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);;

        $result = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['dalam_pemeriksaan'] = $this->core->mysql('reg_periksa')
                  ->select('no_reg')
                  ->select('nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('tgl_registrasi', $date)
                  ->where('stts', 'Berkas Diterima')
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->limit(1)
                  ->oneArray();
                $row['dalam_antrian'] = $this->core->mysql('reg_periksa')
                  ->select(['jumlah' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['sudah_dilayani'] = $this->core->mysql('reg_periksa')
                  ->select(['count' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->where('reg_periksa.stts', 'Sudah')
                  ->oneArray();
                $row['sudah_dilayani']['jumlah'] = 0;
                if(!empty($row['sudah_dilayani'])) {
                  $row['sudah_dilayani']['jumlah'] = $row['sudah_dilayani']['count'];
                }
                $row['selanjutnya'] = $this->core->mysql('reg_periksa')
                  ->select('reg_periksa.no_reg')
                  //->select(['no_urut_reg' => 'ifnull(MAX(CONVERT(RIGHT(reg_periksa.no_reg,3),signed)),0)'])
                  ->select('pasien.nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('reg_periksa.tgl_registrasi', $date)
                  ->where('reg_periksa.stts', 'Belum')
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->asc('reg_periksa.no_reg')
                  ->toArray();
                $row['get_no_reg'] = $this->core->mysql('reg_periksa')
                  ->select(['max' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])
                  ->where('tgl_registrasi', $date)
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['diff'] = (strtotime($row['jam_selesai'])-strtotime($row['jam_mulai']))/60;
                $row['interval'] = 0;
                if($row['diff'] == 0) {
                  $row['interval'] = round($row['diff']/$row['get_no_reg']['max']);
                }
                if($row['interval'] > 10){
                  $interval = 10;
                } else {
                  $interval = $row['interval'];
                }
                foreach ($row['selanjutnya'] as $value) {
                  //$minutes = $value['no_reg'] * $interval;
                  //$row['jam_mulai'] = date('H:i',strtotime('+10 minutes',strtotime($row['jam_mulai'])));
                }

                $result[] = $row;
            }
        }

        return $result;
    }
    /* End Sumbangan Mbak Kiki Sagira RS Bhayangkara Makassar */

    public function getDisplayAntrianPoliDisplay()
    {
        $title = 'Display Antrian Poliklinik';
        $logo  = $this->settings->get('settings.logo');
        $display = $this->_resultDisplayAntrianPoliKodeDisplay();
        $slug = parseURL();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.poli.display.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'vidio' => $this->settings->get('anjungan.vidio'),
          'running_text' => $this->settings->get('anjungan.text_poli'),
          'slug' => $slug,
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayAntrianPoliKodeDisplay()
    {
        $slug = parseURL();

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

        $poliklinik = $slug[3];
        $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari' AND a.kd_poli = '$poliklinik'");
        if(!isset($slug[4]) && $slug[3] == 'all') {
          $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari'");
        }
        if(isset($slug[4]) && $slug[4] != '') {
          $dokter = $slug[4];
          $query = $this->core->mysql()->pdo()->prepare("SELECT a.kd_dokter, a.kd_poli, b.nm_poli, c.nm_dokter, a.jam_mulai, a.jam_selesai FROM jadwal a, poliklinik b, dokter c WHERE a.kd_poli = b.kd_poli AND a.kd_dokter = c.kd_dokter AND a.hari_kerja = '$hari' AND a.kd_poli = '$poliklinik' AND a.kd_dokter = '$dokter'");
        }
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);;

        $result = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['dalam_pemeriksaan'] = $this->core->mysql('reg_periksa')
                  ->select('no_reg')
                  ->select('nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('tgl_registrasi', $date)
                  ->where('stts', 'Berkas Diterima')
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->limit(1)
                  ->oneArray();
                $row['dalam_antrian'] = $this->core->mysql('reg_periksa')
                  ->select(['jumlah' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['sudah_dilayani'] = $this->core->mysql('reg_periksa')
                  ->select(['count' => 'COUNT(DISTINCT reg_periksa.no_rawat)'])
                  ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                  ->where('reg_periksa.tgl_registrasi', date('Y-m-d'))
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->where('reg_periksa.stts', 'Sudah')
                  ->oneArray();
                $row['sudah_dilayani']['jumlah'] = 0;
                if(!empty($row['sudah_dilayani'])) {
                  $row['sudah_dilayani']['jumlah'] = $row['sudah_dilayani']['count'];
                }
                $row['selanjutnya'] = $this->core->mysql('reg_periksa')
                  ->select('reg_periksa.no_reg')
                  //->select(['no_urut_reg' => 'ifnull(MAX(CONVERT(RIGHT(reg_periksa.no_reg,3),signed)),0)'])
                  ->select('pasien.nm_pasien')
                  ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                  ->where('reg_periksa.tgl_registrasi', $date)
                  ->where('reg_periksa.stts', 'Belum')
                  ->where('reg_periksa.kd_poli', $row['kd_poli'])
                  ->where('reg_periksa.kd_dokter', $row['kd_dokter'])
                  ->asc('reg_periksa.no_reg')
                  ->toArray();
                $row['get_no_reg'] = $this->core->mysql('reg_periksa')
                  ->select(['max' => 'ifnull(MAX(CONVERT(RIGHT(no_reg,3),signed)),0)'])
                  ->where('tgl_registrasi', $date)
                  ->where('kd_poli', $row['kd_poli'])
                  ->where('kd_dokter', $row['kd_dokter'])
                  ->oneArray();
                $row['diff'] = (strtotime($row['jam_selesai'])-strtotime($row['jam_mulai']))/60;
                $row['interval'] = 0;
                if($row['diff'] == 0) {
                  $row['interval'] = round($row['diff']/$row['get_no_reg']['max']);
                }
                if($row['interval'] > 10){
                  $interval = 10;
                } else {
                  $interval = $row['interval'];
                }
                foreach ($row['selanjutnya'] as $value) {
                  //$minutes = $value['no_reg'] * $interval;
                  //$row['jam_mulai'] = date('H:i',strtotime('+10 minutes',strtotime($row['jam_mulai'])));
                }

                $result[] = $row;
            }
        }

        return $result;
    }

    public function getDisplayAntrianLoket()
    {
        $title = 'Display Antrian Loket';
        $logo  = $this->settings->get('settings.logo');
        $display = '';

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $show = isset($_GET['show']) ? $_GET['show'] : "";
        switch($show){
          default:
            $display = 'Depan';
            $content = $this->draw('display.antrian.loket.html', [
              'title' => $title,
              'logo' => $logo,
              'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
              'username' => $username,
              'tanggal' => $tanggal,
              'show' => $show,
              'vidio' => $this->settings->get('anjungan.vidio'),
              'running_text' => $this->settings->get('anjungan.text_loket'),
              'display' => $display
            ]);
          break;
          case "panggil_loket":
            $display = 'Panggil Loket';

            $_username = '';
            $__username = 'Tamu';
            if(isset($_SESSION['mlite_user'])) {
              $_username = $this->core->getUserInfo('fullname', null, true);
              $__username = $this->core->getUserInfo('username');
            }
            $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
            $username      = !empty($_username) ? $_username : $__username;

            $setting_antrian_loket = str_replace(",","','", $this->settings->get('anjungan.antrian_loket'));
            $loket = explode(",", $this->settings->get('anjungan.antrian_loket'));
            $get_antrian = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'Loket')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
            $noantrian = 0;
            if(!empty($get_antrian['noantrian'])) {
              $noantrian = $get_antrian['noantrian'];
            }

            $antriloket = $this->settings->get('anjungan.panggil_loket_nomor');
            $tcounter = $antriloket;
            $_tcounter = 1;
            if(!empty($tcounter)) {
              $_tcounter = $tcounter + 1;
            }
            if(isset($_GET['loket'])) {
              $this->core->mysql('mlite_antrian_loket')
                ->where('type', 'Loket')
                ->where('noantrian', $tcounter)
                ->where('postdate', date('Y-m-d'))
                ->save(['end_time' => date('H:i:s')]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_loket')->save(['value' => $_GET['loket']]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_loket_nomor')->save(['value' => $_tcounter]);
            }
            if(isset($_GET['antrian'])) {
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_loket')->save(['value' => $_GET['reset']]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_loket_nomor')->save(['value' => $_GET['antrian']]);
            }
            if(isset($_GET['no_rkm_medis'])) {
              $this->core->mysql('mlite_antrian_loket')->where('noantrian', $_GET['noantrian'])->where('postdate', date('Y-m-d'))->save(['no_rkm_medis' => $_GET['no_rkm_medis']]);
            }
            $hitung_antrian = $this->core->mysql('mlite_antrian_loket')
              ->where('type', 'Loket')
              ->like('postdate', date('Y-m-d'))
              ->toArray();
            $counter = strlen($tcounter);
            $xcounter = [];
            for($i=0;$i<$counter;$i++){
            	$xcounter[] = '<audio id="suarabel'.$i.'" src="{?=url()?}/plugins/anjungan/suara/'.substr($tcounter,$i,1).'.wav" ></audio>';
            };

            $content = $this->draw('display.antrian.loket.html', [
              'title' => $title,
              'logo' => $logo,
              'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
              'username' => $username,
              'tanggal' => $tanggal,
              'show' => $show,
              'loket' => $loket,
              'namaloket' => 'a',
              'panggil_loket' => 'panggil_loket',
              'antrian' => $tcounter,
              'hitung_antrian' => $hitung_antrian,
              'xcounter' => $xcounter,
              'noantrian' =>$noantrian,
              'display' => $display
            ]);
          break;
          case "panggil_cs":
            $display = 'Panggil CS';
            $loket = explode(",", $this->settings->get('anjungan.antrian_cs'));
            $get_antrian = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'CS')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
            $noantrian = 0;
            if(!empty($get_antrian['noantrian'])) {
              $noantrian = $get_antrian['noantrian'];
            }

            $antriloket = $this->settings->get('anjungan.panggil_cs_nomor');
            $tcounter = $antriloket;
            $_tcounter = 1;
            if(!empty($tcounter)) {
              $_tcounter = $tcounter + 1;
            }
            if(isset($_GET['loket'])) {
              $this->core->mysql('mlite_antrian_loket')
                ->where('type', 'CS')
                ->where('noantrian', $tcounter)
                ->where('postdate', date('Y-m-d'))
                ->save(['end_time' => date('H:i:s')]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_cs')->save(['value' => $_GET['loket']]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_cs_nomor')->save(['value' => $_tcounter]);
            }
            if(isset($_GET['antrian'])) {
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_cs')->save(['value' => $_GET['reset']]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_cs_nomor')->save(['value' => $_GET['antrian']]);
            }
            $hitung_antrian = $this->core->mysql('mlite_antrian_loket')
              ->where('type', 'CS')
              ->like('postdate', date('Y-m-d'))
              ->toArray();
            $counter = strlen($tcounter);
            $xcounter = [];
            for($i=0;$i<$counter;$i++){
              $xcounter[] = '<audio id="suarabel'.$i.'" src="{?=url()?}/plugins/anjungan/suara/'.substr($tcounter,$i,1).'.wav" ></audio>';
            };

            $content = $this->draw('display.antrian.loket.html', [
              'title' => $title,
              'logo' => $logo,
              'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
              'username' => $username,
              'tanggal' => $tanggal,
              'show' => $show,
              'loket' => $loket,
              'namaloket' => 'b',
              'panggil_loket' => 'panggil_cs',
              'antrian' => $tcounter,
              'hitung_antrian' => $hitung_antrian,
              'xcounter' => $xcounter,
              'noantrian' =>$noantrian,
              'display' => $display
            ]);
          break;
          case "panggil_apotek":
            $display = 'Panggil Apotek';
            $loket = explode(",", $this->settings->get('anjungan.antrian_apotek'));
            $get_antrian = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'Apotek')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
            $noantrian = 0;
            if(!empty($get_antrian['noantrian'])) {
              $noantrian = $get_antrian['noantrian'];
            }

            $antriloket = $this->settings->get('anjungan.panggil_apotek_nomor');
            $tcounter = $antriloket;
            $_tcounter = 1;
            if(!empty($tcounter)) {
              $_tcounter = $tcounter + 1;
            }
            if(isset($_GET['loket'])) {
              $this->core->mysql('mlite_antrian_loket')
                ->where('type', 'Apotek')
                ->where('noantrian', $tcounter)
                ->where('postdate', date('Y-m-d'))
                ->save(['end_time' => date('H:i:s')]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_apotek')->save(['value' => $_GET['loket']]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_apotek_nomor')->save(['value' => $_tcounter]);
            }
            if(isset($_GET['antrian'])) {
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_apotek')->save(['value' => $_GET['reset']]);
              $this->db('mlite_settings')->where('module', 'anjungan')->where('field', 'panggil_apotek_nomor')->save(['value' => $_GET['antrian']]);
            }
            $hitung_antrian = $this->core->mysql('mlite_antrian_loket')
              ->where('type', 'Apotek')
              ->like('postdate', date('Y-m-d'))
              ->toArray();
            $counter = strlen($tcounter);
            $xcounter = [];
            for($i=0;$i<$counter;$i++){
              $xcounter[] = '<audio id="suarabel'.$i.'" src="{?=url()?}/plugins/anjungan/suara/'.substr($tcounter,$i,1).'.wav" ></audio>';
            };

            $content = $this->draw('display.antrian.loket.html', [
              'title' => $title,
              'logo' => $logo,
              'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
              'username' => $username,
              'tanggal' => $tanggal,
              'show' => $show,
              'loket' => $loket,
              'namaloket' => 'f',
              'panggil_loket' => 'panggil_apotek',
              'antrian' => $tcounter,
              'hitung_antrian' => $hitung_antrian,
              'xcounter' => $xcounter,
              'noantrian' =>$noantrian,
              'display' => $display
            ]);
          break;
        }

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

        //exit();
    }

    public function getDisplayAntrianLoket2()
    {
      $title = 'Display Antrian Loket';
      $logo  = $this->settings->get('settings.logo');
      $display = '';

      $_username = '';
      $__username = 'Tamu';
      if(isset($_SESSION['mlite_user'])) {
        $_username = $this->core->getUserInfo('fullname', null, true);
        $__username = $this->core->getUserInfo('username');
      }
      $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
      $username      = !empty($_username) ? $_username : $__username;

      $show = isset($_GET['show']) ? $_GET['show'] : "";
      switch($show){
        default:
          $display = 'Depan';
          $content = $this->draw('display.antrian.loket2.html', [
            'title' => $title,
            'logo' => $logo,
            'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
            'username' => $username,
            'tanggal' => $tanggal,
            'show' => $show,
            'vidio' => $this->settings->get('anjungan.vidio'),
            'running_text' => $this->settings->get('anjungan.text_loket'),
            'display' => $display
          ]);
        break;
      }
      $assign = [
          'title' => $this->settings->get('settings.nama_instansi'),
          'desc' => $this->settings->get('settings.alamat'),
          'content' => $content
      ];
      $this->setTemplate("canvas.html");
      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);
    }

    public function getDisplayAntrianLaboratorium()
    {
        $logo  = $this->settings->get('settings.logo');
        $title = 'Display Antrian Laboratorium';
        $display = $this->_resultDisplayAntrianLaboratorium();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.laboratorium.html', [
          'logo' => $logo,
          'title' => $title,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'running_text' => $this->settings->get('anjungan.text_laboratorium'),
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

        //exit();
    }

    public function _resultDisplayAntrianLaboratorium()
    {
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

        $poliklinik = $this->settings('settings', 'laboratorium');
        $rows = $this->core->mysql('reg_periksa')
          ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
          ->where('tgl_registrasi', date('Y-m-d'))
          ->where('kd_poli', $poliklinik)
          ->asc('no_reg')
          ->toArray();

        return $rows;
    }

    public function getDisplayAntrianApotek()
    {
        $logo  = $this->settings->get('settings.logo');
        $title = 'Display Antrian Laboratorium';
        $display = $this->_resultDisplayAntrianApotek();

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

        $jadwal = $this->core->mysql('jadwal')->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')->where('hari_kerja', $hari)->toArray();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.antrian.apotek.html', [
          'logo' => $logo,
          'title' => $title,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'running_text' => $this->settings->get('anjungan.text_apotek'),
          'jadwal' => $jadwal,
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayAntrianApotek()
    {
        $query = $this->core->mysql('mlite_antrian_loket')
          ->join('pasien', 'pasien.no_rkm_medis=mlite_antrian_loket.no_rkm_medis')
          ->where('type', 'Apotek')
          ->where('postdate', date('Y-m-d'))
          ->where('status', '<>', '3')
          ->toArray();

        $rows=[];
        foreach ($query as $row) {
          $row['status_resep'] = 'Belum';
          $row['jenis_resep'] = 'Non Racikan';

          $reg_periksa = $this->core->mysql('reg_periksa')
            ->where('tgl_registrasi', date('Y-m-d'))
            ->where('kd_poli', '<>', 'IGDK')
            ->where('no_rkm_medis', $row['no_rkm_medis'])
            ->oneArray();

          $resep_obat = $this->core->mysql('resep_obat')
            ->where('tgl_peresepan', date('Y-m-d'))
            ->where('no_rawat', $reg_periksa['no_rawat'])
            ->where('status', 'ralan')
            ->oneArray();

          $resep_dokter_racikan = $this->core->mysql('resep_dokter_racikan')->where('no_resep', $resep_obat['no_resep'])->oneArray();

          if($resep_obat['tgl_perawatan'] != '0000-00-00' && $resep_obat['jam'] != '00:00:00') {
            $row['status_resep'] = 'Disiapkan';
          }

          if(!empty($resep_dokter_racikan)) {
            $row['jenis_resep'] = 'Racikan';
          }

          $rows[] = $row;
        }

        return $rows;
    }

    public function getDisplayAntrianApotekAmbil()
    {
          $logo  = $this->settings->get('settings.logo');
          $title = 'Display Antrian Farmasi';
          $display = '';

          $_username = '';
          if(isset($_SESSION['mlite_user'])) {
            $_username = $this->core->getUserInfo('fullname', null, true);
          }
          $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
          $username      = !empty($_username) ? $_username : $this->core->getUserInfo('username');

          $content = $this->draw('display.antrian.apotek.ambil.html', [
            'logo' => $logo,
            'title' => $title,
            'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
            'username' => $username,
            'vidio' => $this->settings->get('anjungan.vidio'),
            'tanggal' => $tanggal,
            'running_text' => $this->settings->get('anjungan.text_farmasi'),
            'display' => $display
          ]);

          $assign = [
              'title' => $this->settings->get('settings.nama_instansi'),
              'desc' => $this->settings->get('settings.alamat'),
              'content' => $content
          ];

          $this->setTemplate("canvas.html");

          $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

          //exit();
    }

    public function getDisplayJadwalOperasi()
    {
      $logo  = $this->settings->get('settings.logo');
      $title = 'Display Jadwal Operasi';
      $display = $this->_resultDisplayJadwalOperasi();

      $_username = $this->core->getUserInfo('fullname', null, true);
      $tanggal       = getDayIndonesia(date('Y-m-d')) . ', ' . dateIndonesia(date('Y-m-d'));
      $username      = !empty($_username) ? $_username : $this->core->getUserInfo('username');

      $content = $this->draw('display.jadwal.operasi.html', [
        'logo' => $logo,
        'title' => $title,
        'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
        'username' => $username,
        'tanggal' => $tanggal,
        'running_text' => $this->settings->get('anjungan.text_laboratorium'),
        'display' => $display
      ]);

      $assign = [
        'title' => $this->settings->get('settings.nama_instansi'),
        'desc' => $this->settings->get('settings.alamat'),
        'content' => $content
      ];

      $this->setTemplate("canvas.html");

      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayJadwalOperasi()
    {
      $date = date('Y-m-d');
      $tentukan_hari = date('D', strtotime(date('Y-m-d')));
      $day = array(
        'Sun' => 'AKHAD',
        'Mon' => 'SENIN',
        'Tue' => 'SELASA',
        'Wed' => 'RABU',
        'Thu' => 'KAMIS',
        'Fri' => 'JUMAT',
        'Sat' => 'SABTU'
      );
      $hari = $day[$tentukan_hari];

      $rows = $this->core->mysql('booking_operasi')
        ->join('reg_periksa', 'reg_periksa.no_rawat=booking_operasi.no_rawat')
        ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
        ->join('dokter', 'dokter.kd_dokter=booking_operasi.kd_dokter')
        ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
        ->select(['nm_pasien' => 'pasien.nm_pasien',
                  'no_rkm_medis' => 'reg_periksa.no_rkm_medis',
                  'kd_dokter'    => 'booking_operasi.kd_dokter',
                  'status'       => 'booking_operasi.status',
                  'kode'         => 'dokter.kd_dokter',
                  'namadok'      => 'dokter.nm_dokter',
                  'kd_poli'      => 'reg_periksa.kd_poli',
                  'kodepoli'     => 'poliklinik.kd_poli',
                  'namapoli'     => 'poliklinik.nm_poli',
                  'no_rawat'     => 'booking_operasi.no_rawat'
                ])
        ->where('tanggal', date('Y-m-d'))
        ->toArray();

      $result = [];
      if (count($rows)) {
        foreach ($rows as $row) {
          $norawat = $row['no_rawat'];
          $row['kamar'] = $this->core->mysql('reg_periksa')
            ->join('kamar_inap', 'reg_periksa.no_rawat=kamar_inap.no_rawat')
            ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
            ->join('kamar', 'kamar.kd_kamar=kamar_inap.kd_kamar')
            ->join('bangsal', 'bangsal.kd_bangsal=kamar.kd_bangsal')
            ->where('reg_periksa.no_rawat', $norawat)
            ->where('kamar_inap.tgl_keluar', '0000:00:00')
            ->select([
              'namabangsal' => 'bangsal.nm_bangsal',
              'kodekmr' => 'kamar_inap.kd_kamar'
            ])
            ->select('bangsal.nm_bangsal')
            ->oneArray();

          $result[] = $row;
        }
      }
      return $result;
    }

    public function getPanggilAntrian()
    {
      $res = [];

      $date = date('Y-m-d');
      $sql = $this->core->mysql()->pdo()->prepare("SELECT * FROM mlite_antrian_loket WHERE status = 1 AND postdate = '$date' ORDER BY noantrian ASC");

      if($sql) {
          //$data  = $query->fetch_object();
          $sql->execute();
          $data = $sql->fetchAll(\PDO::FETCH_OBJ);;
          //print_r($data);
          // code...
          switch (strtolower($data[0]->type)) {
              case 'loket':
                  $kode = 'a';
                  break;
              case 'cs':
                  $kode = 'b';
                  break;
              case 'apotek':
                  $kode = 'f';
                  break;
              default:
                  $kode = 'ahhay';
                  break;
          }

          //$terbilang = Terbilang::convert($data->noantrian);
          $terbilang = strtolower(terbilang($data[0]->noantrian));
          $loket = strtolower(terbilang($data[0]->loket));
          $text = "antrian $kode $terbilang counter $loket";

          $res = [
              'id' => $data[0]->kd,
              'status' => true,
              'type' => $data[0]->type,
              'kode' => $kode,
              'noantrian' => $data[0]->noantrian,
              'loket' => $data[0]->loket,
              'panggil' => explode(" ", $text)
          ];

      } else {
          $res = [
              'status' => false
          ];
      }

      die(json_encode($res));

      exit();
    }

    public function getPanggilSelesai()
    {
      if(!isset($_GET['id']) || $_GET['id'] == '') die(json_encode(array('status' => false)));
      $kode  = $_GET['id'];
      $query = $this->core->mysql('mlite_antrian_loket')->where('kd', $kode)->update('status', 2);
      if($query) {
          $res = [
              'status' => true,
              'message' => 'Berhasil update',
          ];
      } else {
          $res = [
              'status' => false,
              'message' => 'Gagal update',
          ];
      }

      die(json_encode($res));
      exit();
    }

    public function getSetPanggil()
    {
      if(!isset($_GET['type']) || $_GET['type'] == '') die(json_encode(array('status' => false,'message' => 'Gagal Type')));
      $type = 'CS';
      if($_GET['type'] == 'loket') {
        $type = 'Loket';
      }
      if($_GET['type'] == 'apotek') {
        $type = 'Apotek';
      }
      $noantrian  = $_GET['noantrian'];
      $loket  = $_GET['loket'];
      $date = date('Y-m-d');
      $query = $this->core->mysql('mlite_antrian_loket')->where('type', $type)->where('noantrian', $noantrian)->where('postdate', $date)->update(['status' => 1, 'loket' => $loket]);
      if($query) {
          $res = [
              'status' => true,
              'message' => 'Berhasil update' .$date,
          ];
      } else {
          $res = [
              'status' => false,
              'message' => 'Gagal update',
          ];
      }

      die(json_encode($res));
      exit();
    }

    public function getSimpanNoRM()
    {
      if(!isset($_GET['no_rkm_medis']) || $_GET['no_rkm_medis'] == '') die(json_encode(array('status' => false,'message' => 'Gagal! No RM Kosong')));
      if(!isset($_GET['type']) || $_GET['type'] == '') die(json_encode(array('status' => false,'message' => 'Gagal! Type antrian salah')));
      if(!isset($_GET['noantrian']) || $_GET['noantrian'] == '') die(json_encode(array('status' => false,'message' => 'Gagal! nomor antrian kosong')));

      $type = '';
      if($_GET['type'] == 'loket') {
        $type = 'Loket';
      }
      if($_GET['type'] == 'cs') {
        $type = 'CS';
      }
      if($_GET['type'] == 'apotek') {
        $type = 'Apotek';
      }

      if(strlen($_GET['no_rkm_medis']) != 6) die(json_encode(array('status' => false,'message' => 'Gagal! Nomor Rekam Medis Tidak Valid')));

      $noantrian  = $_GET['noantrian'];
      $no_rkm_medis = $_GET['no_rkm_medis'];
      $query = $this->core->mysql('mlite_antrian_loket')->where('noantrian', $noantrian)->where('type', $type)->where('postdate', date('Y-m-d'))->update('no_rkm_medis', $no_rkm_medis);
      if($query) {
          $res = [
              'status' => true,
              'message' => 'Berhasil menyimpan No RM :'.$no_rkm_medis,
          ];
          if($type == 'Apotek') {
            $reg_periksa = $this->core->mysql('reg_periksa')->where('tgl_registrasi', date('Y-m-d'))->where('kd_poli', '<>', 'IGDK')->where('no_rkm_medis', $no_rkm_medis)->oneArray();
            $this->core->mysql('resep_obat')
              ->where('tgl_peresepan', date('Y-m-d'))
              ->where('no_rawat', $reg_periksa['no_rawat'])
              ->where('tgl_perawatan', '<>', '0000-00-00')
              ->where('jam', '<>', '00:00:00')
              ->where('status', 'ralan')
              ->save([
                'tgl_penyerahan' => date('Y-m-d'),
                'jam_penyerahan' => date('H:i:s')
              ]);
            $this->core->mysql('mlite_antrian_loket')->where('type', $type)->where('postdate', date('Y-m-d'))->where('no_rkm_medis', $no_rkm_medis)->update('status', 3);
          }
      } else {
          $res = [
              'status' => false,
              'message' => 'Gagal! Terjadi kesalahan pada pada server',
          ];
      }

      die(json_encode($res));
      exit();
    }

    public function getAjax()
    {
      $show = isset($_GET['show']) ? $_GET['show'] : "";
      switch($show){
       default:
        break;
        case "tampilloket":
          $result = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'Loket')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
          $noantrian = '';
          if($result) {
            $noantrian = $result['noantrian'];
          }
        	if($noantrian > 0) {
        		$next_antrian = $noantrian + 1;
        	} else {
        		$next_antrian = 1;
        	}
        	echo '<div id="nomernya" align="center">';
          echo '<h1 class="display-1">';
          echo 'A'.$next_antrian;
          echo '</h1>';
          echo '['.date('Y-m-d').']';
          echo '</div>';
          echo '<br>';
        break;
        case "printloket":
          $result = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'Loket')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
          $noantrian = '';
          if($result) {
            $noantrian = $result['noantrian'];
          }
        	if($noantrian > 0) {
        		$next_antrian = $noantrian + 1;
        	} else {
        		$next_antrian = 1;
        	}
        	echo '<div id="nomernya" align="center">';
          echo '<h1 class="display-1">';
          echo 'A'.$next_antrian;
          echo '</h1>';
          echo '['.date('Y-m-d').']';
          echo '</div>';
          echo '<br>';
          ?>
          <script>
        	$(document).ready(function(){
        		$("#btnKRM").on('click', function(){
        			$("#formloket").submit(function(e){
                e.preventDefault();
                e.stopImmediatePropagation();
        				$.ajax({
        					url: "<?php echo url().'/anjungan/ajax?show=simpanloket&noantrian='.$next_antrian; ?>",
        					type:"POST",
        					data:$(this).serialize(),
        					success:function(data){
        						setTimeout('$("#loading").hide()',1000);
        						//window.location.href = "{?=url('anjungan/pasien')?}";
        						}
        					});
        				return false;
        			});
        		});
        	})
        	</script>
          <?php
        break;
        case "simpanloket":
          $this->core->mysql('mlite_antrian_loket')
            ->save([
              'kd' => NULL,
              'type' => 'Loket',
              'noantrian' => $_GET['noantrian'],
              'postdate' => date('Y-m-d'),
              'start_time' => date('H:i:s'),
              'end_time' => '00:00:00'
            ]);
          //redirect(url('anjungan/pasien'));
        break;
        case "tampilcs":
          $result = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'CS')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
          $noantrian = '';
          if($result) {
            $noantrian = $result['noantrian'];
          }
        	if($noantrian > 0) {
        		$next_antrian = $noantrian + 1;
        	} else {
        		$next_antrian = 1;
        	}
        	echo '<div id="nomernya" align="center">';
          echo '<h1 class="display-1">';
          echo 'B'.$next_antrian;
          echo '</h1>';
          echo '['.date('Y-m-d').']';
          echo '</div>';
          echo '<br>';
        break;
        case "printcs":
          $result = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'CS')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
          $noantrian = '';
          if($result) {
            $noantrian = $result['noantrian'];
          }
        	if($noantrian > 0) {
        		$next_antrian = $noantrian + 1;
        	} else {
        		$next_antrian = 1;
        	}
        	echo '<div id="nomernya" align="center">';
          echo '<h1 class="display-1">';
          echo 'B'.$next_antrian;
          echo '</h1>';
          echo '['.date('Y-m-d').']';
          echo '</div>';
          echo '<br>';
          ?>
          <script>
        	$(document).ready(function(){
        		$("#btnKRMCS").on('click', function(){
        			$("#formcs").submit(function(e){
                e.preventDefault();
                e.stopImmediatePropagation();
        				$.ajax({
        					url: "<?php echo url().'/anjungan/ajax?show=simpancs&noantrian='.$next_antrian; ?>",
        					type:"POST",
        					data:$(this).serialize(),
        					success:function(data){
        						setTimeout('$("#loading").hide()',1000);
        						//window.location.href = "{?=url('anjungan/pasien')?}";
        						}
        					});
        				return false;
        			});
        		});
        	})
        	</script>
          <?php
        break;
        case "simpancs":
          $this->core->mysql('mlite_antrian_loket')
            ->save([
              'kd' => NULL,
              'type' => 'CS',
              'noantrian' => $_GET['noantrian'],
              'postdate' => date('Y-m-d'),
              'start_time' => date('H:i:s'),
              'end_time' => '00:00:00'
            ]);
          //redirect(url('anjungan/pasien'));
        break;

        case "tampilapotek":
          $result = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'Apotek')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
          $noantrian = '';
          if($result) {
            $noantrian = $result['noantrian'];
          }
        	if($noantrian > 0) {
        		$next_antrian = $noantrian + 1;
        	} else {
        		$next_antrian = 1;
        	}
        	echo '<div id="nomernya" align="center">';
          echo '<h1 class="display-1">';
          echo 'F'.$next_antrian;
          echo '</h1>';
          echo '['.date('Y-m-d').']';
          echo '</div>';
          echo '<br>';
        break;
        case "printapotek":
          $result = $this->core->mysql('mlite_antrian_loket')->select('noantrian')->where('type', 'Apotek')->where('postdate', date('Y-m-d'))->desc('start_time')->oneArray();
          $noantrian = '';
          if($result) {
            $noantrian = $result['noantrian'];
          }
        	if($noantrian > 0) {
        		$next_antrian = $noantrian + 1;
        	} else {
        		$next_antrian = 1;
        	}
        	echo '<div id="nomernya" align="center">';
          echo '<h1 class="display-1">';
          echo 'F'.$next_antrian;
          echo '</h1>';
          echo '['.date('Y-m-d').']';
          echo '</div>';
          echo '<br>';
          ?>
          <script>
        	$(document).ready(function(){
        		$("#btnKRM").on('click', function(){
              var no_rkm_medis = $('#norm').val();
        			$("#formloket").submit(function(e){
                e.preventDefault();
                e.stopImmediatePropagation();
        				$.ajax({
        					url: "<?php echo url().'/anjungan/ajax?show=simpanapotek&noantrian='.$next_antrian; ?>",
        					type:"POST",
        					//data:$(this).serialize(),
                  data: {no_rkm_medis:no_rkm_medis},
        					success:function(data){
        						setTimeout('$("#loading").hide()',1000);
        						//window.location.href = "{?=url('anjungan/pasien')?}";
        						}
        					});
        				return false;
        			});
        		});
        	})
        	</script>
          <?php
        break;
        case "simpanapotek":
          $this->core->mysql('mlite_antrian_loket')
            ->save([
              'kd' => NULL,
              'type' => 'Apotek',
              'noantrian' => $_GET['noantrian'],
              'no_rkm_medis' => $_POST['no_rkm_medis'],
              'postdate' => date('Y-m-d'),
              'start_time' => date('H:i:s'),
              'end_time' => '00:00:00'
            ]);
          //redirect(url('anjungan/pasien'));
        break;
        case "loket":
          //$antrian = $this->core->mysql('antriloket')->oneArray();
          //echo $antrian['loket'];
          echo $this->settings->get('anjungan.panggil_loket');
        break;
        case "antriloket":
          //$antrian = $this->core->mysql('antriloket')->oneArray();
          //$antrian = $antrian['antrian'] - 1;
          $antrian = $this->settings->get('anjungan.panggil_loket_nomor') - 1;
          if($antrian == '-1') {
            echo '0';
          } else {
            echo $antrian;
          }
        break;
        case "cs":
          //$antrian = $this->core->mysql('antrics')->oneArray();
          //echo $antrian['loket'];
          echo $this->settings->get('anjungan.panggil_cs');
        break;
        case "antrics":
          //$antrian = $this->core->mysql('antrics')->oneArray();
          //$antrian = $antrian['antrian'] - 1;
          $antrian = $this->settings->get('anjungan.panggil_cs_nomor') - 1;
          if($antrian == '-1') {
            echo '0';
          } else {
            echo $antrian;
          }
        break;
        case "get-skdp":
          if(!empty($_POST['no_rkm_medis'])){
              $data = array();
              $query = $this->core->mysql('skdp_bpjs')
                ->join('dokter', 'dokter.kd_dokter = skdp_bpjs.kd_dokter')
                ->join('booking_registrasi', 'booking_registrasi.tanggal_periksa = skdp_bpjs.tanggal_datang')
                ->join('poliklinik', 'poliklinik.kd_poli = booking_registrasi.kd_poli')
                ->join('pasien', 'pasien.no_rkm_medis = skdp_bpjs.no_rkm_medis')
                ->where('skdp_bpjs.no_rkm_medis', $_POST['no_rkm_medis'])
                ->where('booking_registrasi.kd_poli', $_POST['kd_poli'])
                ->desc('skdp_bpjs.tanggal_datang')
                ->oneArray();
              if(!empty($query)){
                  $data['status'] = 'ok';
                  $data['result'] = $query;
              }else{
                  $data['status'] = 'err';
                  $data['result'] = '';
              }
              echo json_encode($data);
          }
        break;

        case "get-daftar":
          if(!empty($_POST['no_rkm_medis_daftar'])){
              $data = array();
              $query = $this->core->mysql('pasien')
                ->where('no_rkm_medis', $_POST['no_rkm_medis_daftar'])
                ->oneArray();
              if(!empty($query)){
                  $data['status'] = 'ok';
                  $data['result'] = $query;
              }else{
                  $data['status'] = 'err';
                  $data['result'] = '';
              }
              echo json_encode($data);
          }
        break;

        case "get-poli":
          if(!empty($_POST['no_rkm_medis'])){
              $data = array();
              if($this->core->mysql('reg_periksa')->where('no_rkm_medis', $_POST['no_rkm_medis'])->where('tgl_registrasi', $_POST['tgl_registrasi'])->oneArray()) {
                $data['status'] = 'exist';
                $data['result'] = '';
                echo json_encode($data);
              } else {
                $tanggal = $_POST['tgl_registrasi'];
                $tentukan_hari = date('D',strtotime($tanggal));
                $day = array('Sun' => 'AKHAD', 'Mon' => 'SENIN', 'Tue' => 'SELASA', 'Wed' => 'RABU', 'Thu' => 'KAMIS', 'Fri' => 'JUMAT', 'Sat' => 'SABTU');
                $hari=$day[$tentukan_hari];
                $query = $this->core->mysql('jadwal')
                  ->select(['kd_poli' => 'jadwal.kd_poli'])
                  ->select(['nm_poli' => 'poliklinik.nm_poli'])
                  ->select(['jam_mulai' => 'jadwal.jam_mulai'])
                  ->select(['jam_selesai' => 'jadwal.jam_selesai'])
                  ->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')
                  ->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')
                  ->like('jadwal.hari_kerja', $hari)
                  ->toArray();
                if(!empty($query)){
                    $data['status'] = 'ok';
                    $data['result'] = $query;
                }else{
                    $data['status'] = 'err';
                    $data['result'] = '';
                }
                echo json_encode($data);
              }
          }
        break;
        case "get-dokter":
          if(!empty($_POST['kd_poli'])){
              $tanggal = $_POST['tgl_registrasi'];
              $tentukan_hari = date('D',strtotime($tanggal));
              $day = array('Sun' => 'AKHAD', 'Mon' => 'SENIN', 'Tue' => 'SELASA', 'Wed' => 'RABU', 'Thu' => 'KAMIS', 'Fri' => 'JUMAT', 'Sat' => 'SABTU');
              $hari=$day[$tentukan_hari];
              $data = array();
              $result = $this->core->mysql('jadwal')
                ->select(['kd_dokter' => 'jadwal.kd_dokter'])
                ->select(['nm_dokter' => 'dokter.nm_dokter'])
                ->select(['kuota' => 'jadwal.kuota'])
                ->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')
                ->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')
                ->where('jadwal.kd_poli', $_POST['kd_poli'])
                ->like('jadwal.hari_kerja', $hari)
                ->oneArray();
              $check_kuota = $this->core->mysql('reg_periksa')
                ->select(['count' => 'COUNT(DISTINCT no_rawat)'])
                ->where('kd_poli', $_POST['kd_poli'])
                ->where('tgl_registrasi', $_POST['tgl_registrasi'])
                ->oneArray();
              $curr_count = $check_kuota['count'];
              $curr_kuota = $result['kuota'];
              $online = $curr_kuota/2;
              if($curr_count > $online) {
                $data['status'] = 'limit';
              } else {
                $query = $this->core->mysql('jadwal')
                  ->select(['kd_dokter' => 'jadwal.kd_dokter'])
                  ->select(['nm_dokter' => 'dokter.nm_dokter'])
                  ->join('poliklinik', 'poliklinik.kd_poli = jadwal.kd_poli')
                  ->join('dokter', 'dokter.kd_dokter = jadwal.kd_dokter')
                  ->where('jadwal.kd_poli', $_POST['kd_poli'])
                  ->like('jadwal.hari_kerja', $hari)
                  ->toArray();
                if(!empty($query)){
                    $data['status'] = 'ok';
                    $data['result'] = $query;
                }else{
                    $data['status'] = 'err';
                    $data['result'] = '';
                }
                echo json_encode($data);
              }
          }
        break;
        case "get-namapoli":
          //$_POST['kd_poli'] = 'INT';
          if(!empty($_POST['kd_poli'])){
              $data = array();
              $result = $this->core->mysql('poliklinik')->where('kd_poli', $_POST['kd_poli'])->oneArray();
              if(!empty($result)){
                  $data['status'] = 'ok';
                  $data['result'] = $result;
              }else{
                  $data['status'] = 'err';
                  $data['result'] = '';
              }
              echo json_encode($data);
          }
        break;
        case "get-namadokter":
          //$_POST['kd_dokter'] = 'DR001';
          if(!empty($_POST['kd_dokter'])){
              $data = array();
              $result = $this->core->mysql('dokter')->where('kd_dokter', $_POST['kd_dokter'])->oneArray();
              if(!empty($result)){
                  $data['status'] = 'ok';
                  $data['result'] = $result;
              }else{
                  $data['status'] = 'err';
                  $data['result'] = '';
              }
              echo json_encode($data);
          }
        break;
        case "post-registrasi":
          if(!empty($_POST['no_rkm_medis'])){
              $data = array();
              $date = date('Y-m-d');

              $_POST['no_reg']     = $this->core->setNoReg($_POST['kd_dokter'], $_POST['kd_poli']);
              $_POST['hubunganpj'] = $this->core->getPasienInfo('keluarga', $_POST['no_rkm_medis']);
              $_POST['almt_pj']    = $this->core->getPasienInfo('alamat', $_POST['no_rkm_medis']);
              $_POST['p_jawab']    = $this->core->getPasienInfo('namakeluarga', $_POST['no_rkm_medis']);
              $_POST['stts']       = 'Belum';

              $cek_stts_daftar = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $_POST['no_rkm_medis'])->count();
              $_POST['stts_daftar'] = 'Baru';
              if($cek_stts_daftar > 0) {
                $_POST['stts_daftar'] = 'Lama';
              }

              $biaya_reg = $this->core->mysql('poliklinik')->where('kd_poli', $_POST['kd_poli'])->oneArray();
              $_POST['biaya_reg'] = $biaya_reg['registrasi'];
              if($_POST['stts_daftar'] == 'Lama') {
                $_POST['biaya_reg'] = $biaya_reg['registrasilama'];
              }

              $cek_status_poli = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $_POST['no_rkm_medis'])->where('kd_poli', $_POST['kd_poli'])->count();
              $_POST['status_poli'] = 'Baru';
              if($cek_status_poli > 0) {
                $_POST['status_poli'] = 'Lama';
              }

              $tanggal = new \DateTime($this->core->getPasienInfo('tgl_lahir', $_POST['no_rkm_medis']));
              $today = new \DateTime($date);
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
              $_POST['umurdaftar'] = $umur;
              $_POST['sttsumur'] = $sttsumur;
              $_POST['status_lanjut']   = 'Ralan';
              //$_POST['kd_pj']           = $this->settings->get('anjungan.carabayar_umum');
              $_POST['status_bayar']    = 'Belum Bayar';
              $_POST['no_rawat'] = $this->core->setNoRawat($date);
              $_POST['jam_reg'] = date('H:i:s');

              $query = $this->core->mysql('reg_periksa')->save($_POST);

              $result = $this->core->mysql('reg_periksa')
                ->select('reg_periksa.no_rkm_medis')
                ->select('pasien.nm_pasien')
                ->select('pasien.alamat')
                ->select('reg_periksa.tgl_registrasi')
                ->select('reg_periksa.jam_reg')
                ->select('reg_periksa.no_rawat')
                ->select('reg_periksa.no_reg')
                ->select('poliklinik.nm_poli')
                ->select('dokter.nm_dokter')
                ->select('reg_periksa.status_lanjut')
                ->select('penjab.png_jawab')
                ->join('poliklinik', 'poliklinik.kd_poli = reg_periksa.kd_poli')
                ->join('dokter', 'dokter.kd_dokter = reg_periksa.kd_dokter')
                ->join('penjab', 'penjab.kd_pj = reg_periksa.kd_pj')
                ->join('pasien', 'pasien.no_rkm_medis = reg_periksa.no_rkm_medis')
                ->where('reg_periksa.tgl_registrasi', $_POST['tgl_registrasi'])
                ->where('reg_periksa.no_rkm_medis', $_POST['no_rkm_medis'])
                ->oneArray();

              if(!empty($result)){
                  $data['status'] = 'ok';
                  $data['result'] = $result;
              }else{
                  $data['status'] = 'err';
                  $data['result'] = '';
              }
              echo json_encode($data);
          }
        break;
      }
      exit();
    }

    public function getPresensi()
    {

      $title = 'Presensi Pegawai';
      $logo  = $this->settings->get('settings.logo');
      $wallpaper  = $this->settings->get('settings.wallpaper');
      $text_color  = $this->settings->get('settings.text_color');

      $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));

      $content = $this->draw('presensi.html', [
        'title' => $title,
        'notify' => $this->core->getNotify(),
        'logo' => $logo,
        'wallpaper' => $wallpaper,
        'text_color' => $text_color,
        'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
        'tanggal' => $tanggal,
        'running_text' => $this->settings->get('anjungan.text_poli'),
        'jam_jaga' => $this->core->mysql('jam_jaga')->group('jam_masuk')->toArray()
      ]);

      $assign = [
          'title' => $this->settings->get('settings.nama_instansi'),
          'desc' => $this->settings->get('settings.alamat'),
          'content' => $content
      ];

      $this->setTemplate("canvas.html");

      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);
    }

    public function getGeolocation()
    {

      $idpeg = $this->core->mysql('barcode')->where('barcode', $this->core->getUserInfo('username', null, true))->oneArray();

      if(isset($_GET['lat'], $_GET['lng'])) {
          if(!$this->core->mysql('mlite_geolocation_presensi')->where('id', $idpeg['id'])->where('tanggal', date('Y-m-d'))->oneArray()) {
              $this->core->mysql('mlite_geolocation_presensi')
                ->save([
                  'id' => $idpeg['id'],
                  'tanggal' => date('Y-m-d'),
                  'latitude' => $_GET['lat'],
                  'longitude' => $_GET['lng']
              ]);
          }
      }

      exit();
    }

    public function getUpload()
    {
      if ($photo = isset_or($_FILES['webcam']['tmp_name'], false)) {
          $img = new \Systems\Lib\Image;
          if ($img->load($photo)) {
              if ($img->getInfos('width') < $img->getInfos('height')) {
                  $img->crop(0, 0, $img->getInfos('width'), $img->getInfos('width'));
              } else {
                  $img->crop(0, 0, $img->getInfos('height'), $img->getInfos('height'));
              }

              if ($img->getInfos('width') > 512) {
                  $img->resize(512, 512);
              }
              $gambar = uniqid('photo').".".$img->getInfos('type');
          }

          if (isset($img) && $img->getInfos('width')) {

              $img->save(WEBAPPS_PATH."/presensi/".$gambar);

              $urlnya         = WEBAPPS_URL.'/presensi/'.$gambar;
              $barcode        = $_GET['barcode'];

              $idpeg          = $this->core->mysql('barcode')->where('barcode', $barcode)->oneArray();
              $jam_jaga       = $this->core->mysql('jam_jaga')->join('pegawai', 'pegawai.departemen = jam_jaga.dep_id')->where('pegawai.id', $idpeg['id'])->where('jam_jaga.shift', $_GET['shift'])->oneArray();
              $jadwal_pegawai = $this->core->mysql('jadwal_pegawai')->where('id', $idpeg['id'])->where('h'.date('j'), $_GET['shift'])->oneArray();

              $set_keterlambatan  = $this->core->mysql('set_keterlambatan')->toArray();
              $toleransi      = $set_keterlambatan['toleransi'];
              $terlambat1     = $set_keterlambatan['terlambat1'];
              $terlambat2     = $set_keterlambatan['terlambat2'];

              $valid = $this->core->mysql('rekap_presensi')->where('id', $idpeg['id'])->where('shift', $jam_jaga['shift'])->like('jam_datang', '%'.date('Y-m-d').'%')->oneArray();

              if($valid){
                  $this->notify('failure', 'Anda sudah presensi untuk tanggal '.date('Y-m-d'));
              //}elseif((!empty($idpeg['id']))&&(!empty($jam_jaga['shift']))&&($jadwal_pegawai)&&(!$valid)) {
              }elseif((!empty($idpeg['id']))) {
                  $cek = $this->core->mysql('temporary_presensi')->where('id', $idpeg['id'])->oneArray();

                  if(!$cek){
                      if(empty($urlnya)){
                          $this->notify('failure', 'Pilih shift dulu...!!!!');
                      }else{

                          $status = 'Tepat Waktu';

                          if((strtotime(date('Y-m-d H:i:s'))-strtotime(date('Y-m-d').' '.$jam_jaga['jam_masuk']))>($toleransi*60)) {
                            $status = 'Terlambat Toleransi';
                          }
                          if((strtotime(date('Y-m-d H:i:s'))-strtotime(date('Y-m-d').' '.$jam_jaga['jam_masuk']))>($terlambat1*60)) {
                            $status = 'Terlambat I';
                          }
                          if((strtotime(date('Y-m-d H:i:s'))-strtotime(date('Y-m-d').' '.$jam_jaga['jam_masuk']))>($terlambat2*60)) {
                            $status = 'Terlambat II';
                          }

                          if(strtotime(date('Y-m-d H:i:s'))-(date('Y-m-d').' '.$jam_jaga['jam_masuk'])>($toleransi*60)) {
                            $awal  = new \DateTime(date('Y-m-d').' '.$jam_jaga['jam_masuk']);
                            $akhir = new \DateTime();
                            $diff = $akhir->diff($awal,true); // to make the difference to be always positive.
                            $keterlambatan = $diff->format('%H:%I:%S');

                          }

                          $insert = $this->core->mysql('temporary_presensi')
                            ->save([
                              'id' => $idpeg['id'],
                              'shift' => $jam_jaga['shift'],
                              'jam_datang' => date('Y-m-d H:i:s'),
                              'jam_pulang' => NULL,
                              'status' => $status,
                              'keterlambatan' => $keterlambatan,
                              'durasi' => '',
                              'photo' => $urlnya
                            ]);

                          if($insert) {
                            $this->notify('success', 'Presensi Masuk jam '.$jam_jaga['jam_masuk'].' '.$status.' '.$keterlambatan);
                          }
                      }
                  }elseif($cek){

                      $status = $cek['status'];
                      if((strtotime(date('Y-m-d H:i:s'))-strtotime(date('Y-m-d').' '.$jam_jaga['jam_pulang']))<0) {
                        $status = $cek['status'].' & PSW';
                      }

                      $awal  = new \DateTime($cek['jam_datang']);
                      $akhir = new \DateTime();
                      $diff = $akhir->diff($awal,true); // to make the difference to be always positive.
                      $durasi = $diff->format('%H:%I:%S');

                      $ubah = $this->core->mysql('temporary_presensi')
                        ->where('id', $idpeg['id'])
                        ->save([
                          'jam_pulang' => date('Y-m-d H:i:s'),
                          'status' => $status,
                          'durasi' => $durasi
                        ]);

                      if($ubah) {
                          $presensi = $this->core->mysql('temporary_presensi')->where('id', $cek['id'])->oneArray();
                          $insert = $this->core->mysql('rekap_presensi')
                            ->save([
                              'id' => $presensi['id'],
                              'shift' => $presensi['shift'],
                              'jam_datang' => $presensi['jam_datang'],
                              'jam_pulang' => $presensi['jam_pulang'],
                              'status' => $presensi['status'],
                              'keterlambatan' => $presensi['keterlambatan'],
                              'durasi' => $presensi['durasi'],
                              'keterangan' => '-',
                              'photo' => $presensi['photo']
                            ]);
                          if($insert) {
                              $this->notify('success', 'Presensi pulang telah disimpan');
                              $this->core->mysql('temporary_presensi')->where('id', $cek['id'])->delete();
                          }
                      }
                  }
              }else{
                  $this->notify('failure', 'ID Pegawai atau jadwal shift tidak sesuai. Silahkan pilih berdasarkan shift departemen anda!');
              }
          }
      }
      //echo 'Upload';
      exit();
    }

    public function getDisplayBed()
    {
        $title = 'Display Bed Management';
        $logo  = $this->settings->get('settings.logo');
        $display = $this->_resultDisplayBed();

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('display.bed.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'running_text' => $this->settings->get('anjungan.text_poli'),
          'display' => $display
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function _resultDisplayBed()
    {

        $query = $this->core->mysql()->pdo()->prepare("SELECT a.nm_bangsal, b.kelas , a.kd_bangsal FROM bangsal a, kamar b WHERE a.kd_bangsal = b.kd_bangsal AND b.statusdata = '1' GROUP BY b.kd_bangsal, b.kelas");
        $query->execute();
        $rows = $query->fetchAll(\PDO::FETCH_ASSOC);;

        $result = [];
        if (count($rows)) {
            foreach ($rows as $row) {
                $row['kosong'] = $this->core->mysql('kamar')
                  ->select(['jumlah' => 'COUNT(kamar.status)'])
                  ->join('bangsal', 'bangsal.kd_bangsal = kamar.kd_bangsal')
                  ->where('bangsal.kd_bangsal', $row['kd_bangsal'])
                  ->where('kamar.kelas',$row['kelas'])
                  ->where('kamar.status','KOSONG')
                  ->where('kamar.statusdata','1')
                  ->group(array('kamar.kd_bangsal','kamar.kelas'))
                  ->oneArray();
                if(empty($row['kosong']['jumlah'])) {
                  $row['kosong']['jumlah'] = 0;
                }
                $row['isi'] = $this->core->mysql('kamar')
                  ->select(['jumlah' => 'COUNT(kamar.status)'])
                  ->join('bangsal', 'bangsal.kd_bangsal = kamar.kd_bangsal')
                  ->where('bangsal.kd_bangsal', $row['kd_bangsal'])
                  ->where('kamar.kelas',$row['kelas'])
                  ->where('kamar.status','ISI')
                  ->where('kamar.statusdata','1')
                  ->group(array('kamar.kd_bangsal','kamar.kelas'))
                  ->oneArray();
                if(empty($row['isi']['jumlah'])) {
                  $row['isi']['jumlah'] = 0;
                }
                $result[] = $row;
            }
        }

        return $result;
    }

    public function getSepMandiri()
    {
        $title = 'Display SEP Mandiri';
        $logo  = $this->settings->get('settings.logo');

        $_username = '';
        $__username = 'Tamu';
        if(isset($_SESSION['mlite_user'])) {
          $_username = $this->core->getUserInfo('fullname', null, true);
          $__username = $this->core->getUserInfo('username');
        }
        $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
        $username      = !empty($_username) ? $_username : $__username;

        $content = $this->draw('sep.mandiri.html', [
          'title' => $title,
          'logo' => $logo,
          'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
          'username' => $username,
          'tanggal' => $tanggal,
          'running_text' => $this->settings->get('anjungan.text_anjungan'),
        ]);

        $assign = [
            'title' => $this->settings->get('settings.nama_instansi'),
            'desc' => $this->settings->get('settings.alamat'),
            'content' => $content
        ];

        $this->setTemplate("canvas.html");

        $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function getSepMandiriCek()
    {
      if(isset($_POST['cekrm']) && isset($_POST['no_rkm_medis']) && $_POST['no_rkm_medis'] !='') {
        $pasien = $this->core->mysql('pasien')->where('no_rkm_medis', $_POST['no_rkm_medis'])->oneArray();
        redirect(url('anjungan/sep/'.$pasien['no_peserta'].'/'.$_POST['no_rkm_medis']));
      } else {
        redirect(url('anjungan/sep'));
      }
      exit();
    }

    public function getSepMandiriNokaNorm()
    {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consid.$this->secretkey.$tStamp;

      date_default_timezone_set($this->settings->get('settings.timezone'));
      $slug = parseURL();
      $sep_response = '';
      if (count($slug) == 4 && $slug[0] == 'anjungan' && $slug[1] == 'sep') {

          $url = "Rujukan/List/Peserta/".$slug[2];

          $url = $this->api_url.''.$url;
          $output = BpjsService::get($url, NULL, $this->consid, $this->secretkey, $this->user_key, $tStamp);
          $json = json_decode($output, true);
          //var_dump($json);
          if($json['metaData']['code'] == 201){
            $url = "Rujukan/RS/List/Peserta/" . $slug[2];

            $url = $this->api_url . '' . $url;
            $output = BpjsService::get($url, NULL, $this->consid, $this->secretkey, $this->user_key, $tStamp);
            $json = json_decode($output, true);
          }
          $code = $json['metaData']['code'];
          $message = $json['metaData']['message'];
          $stringDecrypt = stringDecrypt($key, $json['response']);
          $decompress = '""';

          if(!empty($json)):
            if ($code != "200") {
              $sep_response = $message;
            } else {
              if(!empty($stringDecrypt)) {
                $decompress = decompress($stringDecrypt);
                $sep_response = json_decode($decompress, true);
              } else {
                $sep_response = "Sambungan ke server BPJS sedang ada gangguan. Silahkan ulangi lagi dengan menekan tombol REFRESH";
              }
            }
          else:
            $sep_response = "Sambungan ke server BPJS sedang ada gangguan. Silahkan ulangi lagi dengan menekan tombol REFRESH";
          endif;
      }

      $title = 'Display SEP Mandiri';
      $logo  = $this->settings->get('settings.logo');

      $_username = '';
      $__username = 'Tamu';
      if(isset($_SESSION['mlite_user'])) {
        $_username = $this->core->getUserInfo('fullname', null, true);
        $__username = $this->core->getUserInfo('username');
      }
      $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
      $username      = !empty($_username) ? $_username : $__username;

      $content = $this->draw('sep.mandiri.noka.norm.html', [
        'title' => $title,
        'logo' => $logo,
        'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
        'username' => $username,
        'tanggal' => $tanggal,
        'running_text' => $this->settings->get('anjungan.text_anjungan'),
        'no_rkm_medis' => $slug[3],
        'sep_response' => $sep_response
      ]);

      $assign = [
          'title' => $this->settings->get('settings.nama_instansi'),
          'desc' => $this->settings->get('settings.alamat'),
          'content' => $content
      ];

      $this->setTemplate("canvas.html");

      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);
    }

    public function getSepMandiriBikin()
    {
      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
      $key = $this->consid.$this->secretkey.$tStamp;

      date_default_timezone_set($this->settings->get('settings.timezone'));
      $slug = parseURL();

      $title = 'Display SEP Mandiri';
      $logo  = $this->settings->get('settings.logo');
      $kode_ppk  = $this->settings->get('settings.ppk_bpjs');
      $nama_ppk  = $this->settings->get('settings.nama_instansi');

      $_username = '';
      $__username = 'Tamu';
      if(isset($_SESSION['mlite_user'])) {
        $_username = $this->core->getUserInfo('fullname', null, true);
        $__username = $this->core->getUserInfo('username');
      }
      $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
      $username      = !empty($_username) ? $_username : $__username;

      $date = date('Y-m-d');

      $url = 'Rujukan/'.$slug[3];
      $url = $this->api_url.''.$url;
      $output = BpjsService::get($url, NULL, $this->consid, $this->secretkey, $this->user_key, $tStamp);
      $json = json_decode($output, true);

      if($json['metaData']['code'] == 201){
        $url = 'Rujukan/RS/' . $slug[3];

        $url = $this->api_url . '' . $url;
        $output = BpjsService::get($url, NULL, $this->consid, $this->secretkey, $this->user_key, $tStamp);
        $json = json_decode($output, true);
      }

      //var_dump($json);
      $code = $json['metaData']['code'];
      $message = $json['metaData']['message'];
      $stringDecrypt = stringDecrypt($key, $json['response']);
      $decompress = '""';
      //$rujukan = [];
      if ($code == "200") {
          $decompress = decompress($stringDecrypt);
          $rujukan = json_decode($decompress, true);
      }

      if(!$this->core->mysql('reg_periksa')->where('no_rkm_medis', $slug[4])->where('tgl_registrasi', $date)->oneArray()) {
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

        $maping_poli_bpjs = $this->core->mysql('maping_poli_bpjs')->where('kd_poli_bpjs', $slug[5])->oneArray();
        $jadwal = $this->core->mysql('jadwal')->where('hari_kerja', $hari)->where('kd_poli', $maping_poli_bpjs['kd_poli_rs'])->oneArray();
        $poliklinik = $this->core->mysql('poliklinik')->where('kd_poli', $jadwal['kd_poli'])->oneArray();

        $pasien = $this->core->mysql('pasien')->where('no_rkm_medis', $slug['4'])->oneArray();

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

        $insert = $this->core->mysql('reg_periksa')
          ->save([
            'no_reg' => $this->core->setNoReg($jadwal['kd_dokter'], $jadwal['kd_poli']),
            'no_rawat' => $this->core->setNoRawat(date('Y-m-d')),
            'tgl_registrasi' => date('Y-m-d'),
            'jam_reg' => date('H:i:s'),
            'kd_dokter' => $jadwal['kd_dokter'],
            'no_rkm_medis' => $slug['4'],
            'kd_poli' => $jadwal['kd_poli'],
            'p_jawab' => $this->core->getPasienInfo('namakeluarga', $slug['4']),
            'almt_pj' => $this->core->getPasienInfo('alamatpj', $slug['4']),
            'hubunganpj' => $this->core->getPasienInfo('keluarga', $slug['4']),
            'biaya_reg' => $poliklinik['registrasi'],
            'stts' => 'Belum',
            'stts_daftar' => 'Baru',
            'status_lanjut' => 'Ralan',
            'kd_pj' => 'BPJ',
            'umurdaftar' => $umur_daftar,
            'sttsumur' => $status_umur,
            'status_bayar' => 'Belum Bayar',
            'status_poli' => 'Baru'
          ]);

      }

      $reg_periksa = $this->core->mysql('reg_periksa')
        ->join('pasien', 'pasien.no_rkm_medis=reg_periksa.no_rkm_medis')
        ->join('poliklinik', 'poliklinik.kd_poli=reg_periksa.kd_poli')
        ->where('reg_periksa.tgl_registrasi', $date)
        ->where('reg_periksa.no_rkm_medis', $slug[4])
        ->oneArray();

      $no_surat_kontrol_bpjs = "";
      $dpjp = $this->core->mysql('maping_dokter_dpjpvclaim')->where('kd_dokter', $reg_periksa['kd_dokter'])->oneArray();
      //$skdp_bpjs = $this->core->mysql('skdp_bpjs')->where('no_rkm_medis', $slug[4])->where('tanggal_datang', $date)->oneArray();
      $surat_kontrol_bpjs = $this->core->mysql('bridging_surat_kontrol_bpjs')
        ->select('no_surat')
        ->join('bridging_sep', 'bridging_sep.no_sep=bridging_surat_kontrol_bpjs.no_sep')
        ->where('bridging_sep.nomr', $slug[4])
        ->where('tgl_rencana', $date)
        ->oneArray();

      if(!$surat_kontrol_bpjs){
        $cari_rujukan = $this->core->mysql('bridging_sep')->where('no_rujukan',$slug[3])->where('kdpolitujuan',$rujukan['rujukan']['poliRujukan']['kode'])->asc('tglsep')->oneArray();
        if($cari_rujukan){
          $skdp_bpjs = $this->createKontrol($slug[3],$rujukan['rujukan']['poliRujukan']['kode'],$dpjp['kd_dokter_bpjs']);
          $no_surat_kontrol_bpjs = $skdp_bpjs;
        }
      }else{
        $no_surat_kontrol_bpjs = $surat_kontrol_bpjs['no_surat'];
      }

      $content = $this->draw('sep.mandiri.bikin.html', [
        'title' => $title,
        'logo' => $logo,
        'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
        'username' => $username,
        'tanggal' => $tanggal,
        'running_text' => $this->settings->get('anjungan.text_anjungan'),
        'kode_ppk' => $kode_ppk,
        'nama_ppk' => $nama_ppk,
        'reg_periksa' => $reg_periksa,
        'skdp_bpjs' => $no_surat_kontrol_bpjs,
        'rujukan' => $rujukan,
        'dpjp' => $dpjp
      ]);

      $assign = [
          'title' => $this->settings->get('settings.nama_instansi'),
          'desc' => $this->settings->get('settings.alamat'),
          'content' => $content
      ];

      $this->setTemplate("canvas.html");

      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);
    }

    public function postSaveSEP()
    {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
        $key = $this->consid.$this->secretkey.$tStamp;

        $_POST['kdppkpelayanan'] = $this->settings->get('settings.ppk_bpjs');
        $_POST['nmppkpelayanan'] = $this->settings->get('settings.nama_instansi');
        $_POST['sep_user']	= 'SEP Mandiri';

        if($this->settings->get('jkn_mobile.kirimantrian') == 'ya') {
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

          $pasien = $this->core->mysql('pasien')->where('no_rkm_medis', $_POST['nomr'])->oneArray();
          $reg_periksa = $this->core->mysql('reg_periksa')->where('tgl_registrasi', date('Y-m-d'))->where('no_rkm_medis', $_POST['nomr'])->oneArray();
          $maping_dokter_dpjpvclaim = $this->core->mysql('maping_dokter_dpjpvclaim')->where('kd_dokter', $reg_periksa['kd_dokter'])->oneArray();
          $maping_poli_bpjs = $this->core->mysql('maping_poli_bpjs')->where('kd_poli_rs', $reg_periksa['kd_poli'])->oneArray();
          $jadwaldokter = $this->core->mysql('jadwal')->where('kd_dokter', $reg_periksa['kd_dokter'])->where('kd_poli', $reg_periksa['kd_poli'])->where('hari_kerja', $hari)->oneArray();

          $no_urut_reg = substr($reg_periksa['no_reg'], 0, 3);
          $minutes = $no_urut_reg * 10;
          $cek_kuota['jam_mulai'] = date('H:i:s',strtotime('+'.$minutes.' minutes',strtotime($jadwaldokter['jam_mulai'])));

          $kodebooking = $this->settings->get('settings.ppk_bpjs').''.convertNorawat($reg_periksa['no_rawat']).''.$maping_poli_bpjs['kd_poli_bpjs'].''.$reg_periksa['no_reg'];

          $nomorreferensi = $_POST['norujukan'];
          if(isset($_POST['tujuanKunj']) == '3') {
            $nomorreferensi = $_POST['noskdp'];
          }
          $data = [
              'kodebooking' => $kodebooking,
              'jenispasien' => 'JKN',
              'nomorkartu' => $pasien['no_peserta'],
              'nik' => $pasien['no_ktp'],
              'nohp' => $pasien['no_tlp'],
              'kodepoli' => $maping_poli_bpjs['kd_poli_bpjs'],
              'namapoli' => $maping_poli_bpjs['nm_poli_bpjs'],
              'pasienbaru' => '0',
              'norm' => $_POST['nomr'],
              'tanggalperiksa' => date('Y-m-d'),
              'kodedokter' => $maping_dokter_dpjpvclaim['kd_dokter_bpjs'],
              'namadokter' => $maping_dokter_dpjpvclaim['nm_dokter_bpjs'],
              'jampraktek' => substr($jadwaldokter['jam_mulai'],0,5).'-'.substr($jadwaldokter['jam_selesai'],0,5),
              'jeniskunjungan' => $_POST['tujuanKunj'],
              'nomorreferensi' => $nomorreferensi,
              'nomorantrean' => $maping_poli_bpjs['kd_poli_bpjs'].'-'.$reg_periksa['no_reg'],
              'angkaantrean' => $reg_periksa['no_reg'],
              'estimasidilayani' => strtotime($reg_periksa['tgl_registrasi'].' '.$cek_kuota['jam_mulai']) * 1000,
              'sisakuotajkn' => $jadwaldokter['kuota']-ltrim($reg_periksa['no_reg'],'0'),
              'kuotajkn' => intval($jadwaldokter['kuota']),
              'sisakuotanonjkn' => $jadwaldokter['kuota']-ltrim($reg_periksa['no_reg'],'0'),
              'kuotanonjkn' => intval($jadwaldokter['kuota']),
              'keterangan' => 'Peserta harap 30 menit lebih awal guna pencatatan administrasi.'
          ];
          // echo 'Request:<br>';
          // echo "<pre>".print_r($data,true)."</pre>";
          $data = json_encode($data);
          $url = $this->api_url.'antrean/add';
          $output = BpjsService::post($url, $data, $this->consid, $this->secretkey, $this->user_key, NULL);
          // $data = json_decode($output, true);
        }

        $data = [
            'request' => [
               't_sep' => [
                  'noKartu' => $_POST['no_kartu'],
                  'tglSep' => $_POST['tglsep'],
                  'ppkPelayanan' => $_POST['kdppkpelayanan'],
                  'jnsPelayanan' => $_POST['jnspelayanan'],
                  'klsRawat' => [
                      'klsRawatHak' => $_POST['klsrawat'],
                      'klsRawatNaik' => '',
                      'pembiayaan' => '',
                      'penanggungJawab' => ''
                  ],
                  'noMR' => $_POST['nomr'],
                  'rujukan' => [
                     'asalRujukan' => $_POST['asal_rujukan'],
                     'tglRujukan' => $_POST['tglrujukan'],
                     'noRujukan' => $_POST['norujukan'],
                     'ppkRujukan' => $_POST['kdppkrujukan']
                  ],
                  'catatan' => $_POST['catatan'],
                  'diagAwal' => $_POST['diagawal'],
                  'poli' => [
                     'tujuan' => $_POST['kdpolitujuan'],
                     'eksekutif' => $_POST['eksekutif']
                  ],
                  'cob' => [
                     'cob' => $_POST['cob']
                  ],
                  'katarak' => [
                     'katarak' => $_POST['katarak']
                  ],
                  'jaminan' => [
                     'lakaLantas' => $_POST['lakalantas'],
                     'noLP' => '',
                     'penjamin' => [
                         'tglKejadian' => $_POST['tglkkl'],
                         'keterangan' => $_POST['keterangankkl'],
                         'suplesi' => [
                             'suplesi' => $_POST['suplesi'],
                             'noSepSuplesi' => $_POST['no_sep_suplesi'],
                             'lokasiLaka' => [
                                 'kdPropinsi' => $_POST['kdprop'],
                                 'kdKabupaten' => $_POST['kdkab'],
                                 'kdKecamatan' => $_POST['kdkec']
                             ]
                         ]
                     ]
                  ],
                  'tujuanKunj' => $_POST['tujuanKunj'],
                  'flagProcedure' => $_POST['flagProcedure'],
                  'kdPenunjang' => $_POST['kdPenunjang'],
                  'assesmentPel' => $_POST['assesmentPel'],
                  'skdp' => [
                     'noSurat' => $_POST['noskdp'],
                     'kodeDPJP' => $_POST['kddpjp']
                  ],
                  'dpjpLayan' => $_POST['kddpjp'],
                  'noTelp' => $_POST['notelep'],
                  'user' => $_POST['sep_user']
               ]
            ]
        ];

        $data = json_encode($data);

        //echo $data;
        $url = $this->api_url.'SEP/2.0/insert';
        $output = BpjsService::post($url, $data, $this->consid, $this->secretkey, $this->user_key, $tStamp);
        $data = json_decode($output, true);

        if($data == NULL) {

          echo 'Koneksi ke server BPJS terputus. Silahkan ulangi beberapa saat lagi!';

        } else if($data['metaData']['code'] == 200){

          $code = $data['metaData']['code'];
          $message = $data['metaData']['message'];

          $stringDecrypt = stringDecrypt($key, $data['response']);
          $decompress = '""';
          if(!empty($stringDecrypt)) {
            $decompress = decompress($stringDecrypt);
          }

          $data = '{
            "metaData": {
              "code": "'.$code.'",
              "message": "'.$message.'"
            },
            "response": '.$decompress.'}';

          $data = json_decode($data, true);

          $_POST['sep_no_sep'] = $data['response']['sep']['noSep'];

          $simpan_sep = $this->core->mysql('bridging_sep')->save([
            'no_sep' => $_POST['sep_no_sep'],
            'no_rawat' => $_POST['no_rawat'],
            'tglsep' => $_POST['tglsep'],
            'tglrujukan' => $_POST['tglrujukan'],
            'no_rujukan' => $_POST['norujukan'],
            'kdppkrujukan' => $_POST['kdppkrujukan'],
            'nmppkrujukan' => $_POST['nmppkrujukan'],
            'kdppkpelayanan' => $_POST['kdppkpelayanan'],
            'nmppkpelayanan' => $_POST['nmppkpelayanan'],
            'jnspelayanan' => $_POST['jnspelayanan'],
            'catatan' => $_POST['catatan'],
            'diagawal' => $_POST['diagawal'],
            'nmdiagnosaawal' => $_POST['nmdiagnosaawal'],
            'kdpolitujuan' => $_POST['kdpolitujuan'],
            'nmpolitujuan' => $_POST['nmpolitujuan'],
            'klsrawat' => $_POST['klsrawat'],
            'klsnaik' => '',
            'pembiayaan' => '',
            'pjnaikkelas' => '',
            'lakalantas' => $_POST['lakalantas'],
            'user' => $_POST['sep_user'],
            'nomr' => $_POST['nomr'],
            'nama_pasien' => $_POST['nama_pasien'],
            'tanggal_lahir' => $_POST['tanggal_lahir'],
            'peserta' => $_POST['peserta'],
            'jkel' => $_POST['jenis_kelamin'],
            'no_kartu' => $_POST['no_kartu'],
            'tglpulang' => $_POST['tglpulang'],
            'asal_rujukan' => $_POST['asal_rujukan'],
            'eksekutif' => $_POST['eksekutif'],
            'cob' => $_POST['cob'],
            'notelep' => $_POST['notelep'],
            'katarak' => $_POST['katarak'],
            'tglkkl' => $_POST['tglkkl'],
            'keterangankkl' => $_POST['keterangankkl'],
            'suplesi' => $_POST['suplesi'],
            'no_sep_suplesi' => $_POST['no_sep_suplesi'],
            'kdprop' => $_POST['kdprop'],
            'nmprop' => $_POST['nmprop'],
            'kdkab' => $_POST['kdkab'],
            'nmkab' => $_POST['nmkab'],
            'kdkec' => $_POST['kdkec'],
            'nmkec' => $_POST['nmkec'],
            'noskdp' => $_POST['noskdp'],
            'kddpjp' => $_POST['kddpjp'],
            'nmdpdjp' => $_POST['nmdpdjp'],
            'tujuankunjungan' => $_POST['tujuanKunj'],
            'flagprosedur' => $_POST['flagProcedure'],
            'penunjang' => $_POST['kdPenunjang'],
            'asesmenpelayanan' => $_POST['assesmentPel'],
            'kddpjplayanan' => $_POST['kddpjp'],
            'nmdpjplayanan' => $_POST['nmdpdjp']
          ]);

          if($simpan_sep) {
            if($_POST['prolanis_prb'] !=='') {
              $simpan_prb = $this->core->mysql('bpjs_prb')->save([
                'no_sep' => $_POST['sep_no_sep'],
                'prb' => $_POST['prolanis_prb']
              ]);
            }
            echo $_POST['sep_no_sep'];
          } else {
            $simpan_sep = $this->core->mysql('bridging_sep_internal')->save([
              'no_sep' => $_POST['sep_no_sep'],
              'no_rawat' => $_POST['no_rawat'],
              'tglsep' => $_POST['tglsep'],
              'tglrujukan' => $_POST['tglrujukan'],
              'no_rujukan' => $_POST['norujukan'],
              'kdppkrujukan' => $_POST['kdppkrujukan'],
              'nmppkrujukan' => $_POST['nmppkrujukan'],
              'kdppkpelayanan' => $_POST['kdppkpelayanan'],
              'nmppkpelayanan' => $_POST['nmppkpelayanan'],
              'jnspelayanan' => $_POST['jnspelayanan'],
              'catatan' => $_POST['catatan'],
              'diagawal' => $_POST['diagawal'],
              'nmdiagnosaawal' => $_POST['nmdiagnosaawal'],
              'kdpolitujuan' => $_POST['kdpolitujuan'],
              'nmpolitujuan' => $_POST['nmpolitujuan'],
              'klsrawat' => $_POST['klsrawat'],
              'klsnaik' => $_POST['klsnaik'],
              'pembiayaan' => $_POST['pembiayaan'],
              'pjnaikkelas' => $_POST['pjnaikkelas'],
              'lakalantas' => $_POST['lakalantas'],
              'user' => $_POST['sep_user'],
              'nomr' => $_POST['nomr'],
              'nama_pasien' => $_POST['nama_pasien'],
              'tanggal_lahir' => $_POST['tanggal_lahir'],
              'peserta' => $_POST['peserta'],
              'jkel' => $_POST['jenis_kelamin'],
              'no_kartu' => $_POST['no_kartu'],
              'tglpulang' => $_POST['tglpulang'],
              'asal_rujukan' => $_POST['asal_rujukan'],
              'eksekutif' => $_POST['eksekutif'],
              'cob' => $_POST['cob'],
              'notelep' => $_POST['notelep'],
              'katarak' => $_POST['katarak'],
              'tglkkl' => $_POST['tglkkl'],
              'keterangankkl' => $_POST['keterangankkl'],
              'suplesi' => $_POST['suplesi'],
              'no_sep_suplesi' => $_POST['no_sep_suplesi'],
              'kdprop' => $_POST['kdprop'],
              'nmprop' => $_POST['nmprop'],
              'kdkab' => $_POST['kdkab'],
              'nmkab' => $_POST['nmkab'],
              'kdkec' => $_POST['kdkec'],
              'nmkec' => $_POST['nmkec'],
              'noskdp' => $_POST['noskdp'],
              'kddpjp' => $_POST['kddpjp'],
              'nmdpdjp' => $_POST['nmdpdjp'],
              'tujuankunjungan' => $_POST['tujuanKunj'],
              'flagprosedur' => $_POST['flagProcedure'],
              'penunjang' => $_POST['kdPenunjang'],
              'asesmenpelayanan' => $_POST['assesmentPel'],
              'kddpjplayanan' => $_POST['kddpjppelayanan'],
              'nmdpjplayanan' => $_POST['nmdpjppelayanan']
            ]);
          }

        } else {

          echo $data['metaData']['message'];

        }
        exit();

    }

    public function getCetakSEP()
    {
        $slug = parseURL();
        $no_sep = $slug[3];
        $settings = $this->settings('settings');
        $this->tpl->set('settings', $this->tpl->noParse_array(htmlspecialchars_array($settings)));
        $data_sep = $this->core->mysql('bridging_sep')->where('no_sep', $no_sep)->oneArray();
        if(!$data_sep) {
          $data_sep = $this->core->mysql('bridging_sep_internal')->where('no_sep', $no_sep)->oneArray();
        }
        $batas_rujukan = strtotime('+87 days', strtotime($data_sep['tglrujukan']));

        $qr=QRCode::getMinimumQRCode($data_sep['no_sep'],QR_ERROR_CORRECT_LEVEL_L);
        //$qr=QRCode::getMinimumQRCode('Petugas: '.$this->core->getUserInfo('fullname', null, true).'; Lokasi: '.UPLOADS.'/invoices/'.$result['kd_billing'].'.pdf',QR_ERROR_CORRECT_LEVEL_L);
        $im=$qr->createImage(4,4);
        imagepng($im,BASE_DIR.'/tmp/qrcode.png');
        imagedestroy($im);

        $image = "/tmp/qrcode.png";

        $data_sep['qrCode'] = url($image);
        $data_sep['batas_rujukan'] = date('Y-m-d', $batas_rujukan);
        $potensi_prb = $this->core->mysql('bpjs_prb')->where('no_sep', $no_sep)->oneArray();
        $data_sep['potensi_prb'] = $potensi_prb['prb'];

        echo $this->draw('cetak.sep.html', ['data_sep' => $data_sep]);
        $this->core->mysql('mutasi_berkas')->save([
          'no_rawat' => $data_sep['no_rawat'],
          'status' => 'Sudah Dikirim',
          'dikirim' => date('Y-m-d H:i:s'),
          'diterima' => '0000-00-00 00:00:00',
          'kembali' => '0000-00-00 00:00:00',
          'tidakada' => '0000-00-00 00:00:00',
          'ranap' => '0000-00-00 00:00:00'
        ]);
        exit();
    }

    public function createKontrol($rujukan,$poli,$dokter)
    {
      $date = date('Y-m-d');
      $cari_rujukan = $this->core->mysql('bridging_sep')->where('no_rujukan',$rujukan)->where('kdpolitujuan',$poli)->asc('tglsep')->oneArray();
      $dpjp = $this->core->mysql('maping_dokter_dpjpvclaim')->where('kd_dokter', $dokter)->oneArray();
      $nmPoli = $this->core->mysql('maping_poli_bpjs')->where('kd_poli_bpjs', $poli)->oneArray();

      date_default_timezone_set('UTC');
      $tStamp = strval(time() - strtotime('1970-01-01 00:00:00'));
      $key = $this->consid.$this->secretkey.$tStamp;
      $_POST['kdppkpelayanan'] = $this->settings->get('settings.ppk_bpjs');
      $_POST['nmppkpelayanan'] = $this->settings->get('settings.nama_instansi');
      $_POST['sep_user']  = 'SEP Mandiri';

      $data = [
        'request' => [
            'noSEP' => $cari_rujukan['no_sep'],
            'kodeDokter' => $dokter,
            'poliKontrol' => $poli,
            'tglRencanaKontrol' => $date,
            'user' => $_POST['sep_user']
        ]
      ];

      $data = json_encode($data);
      // echo $data;
      $url = $this->api_url . 'RencanaKontrol/insert';
      $output = BpjsService::post($url, $data, $this->consid, $this->secretkey, $this->user_key, $tStamp);
      $data = json_decode($output, true);

      //$noKontrol = $data['metaData']['message'];
      $noKontrol = '';
      if ($data == NULL) {

        echo 'Koneksi ke server BPJS terputus. Silahkan ulangi beberapa saat lagi!';
      } else if ($data['metaData']['code'] == 200) {
        $stringDecrypt = stringDecrypt($key, $data['response']);
        $decompress = '""';
        //$rujukan = [];
        $decompress = decompress($stringDecrypt);
        $rujukan = json_decode($decompress, true);
        //var_dump($rujukan);
        $noKontrol = $rujukan['noSuratKontrol'];

        $simpanKontrol = $this->core->mysql('bridging_surat_kontrol_bpjs')->save([
          'no_sep' => $cari_rujukan['no_sep'],
          'tgl_surat' => $cari_rujukan['tglsep'],
          'no_surat' => $noKontrol,
          'tgl_rencana' => $date,
          'kd_dokter_bpjs' => $dokter,
          'nm_dokter_bpjs' => $dpjp['nm_dokter_bpjs'],
          'kd_poli_bpjs' => $poli,
          'nm_poli_bpjs' => $nmPoli['nm_poli_bpjs']
        ]);
        if($simpanKontrol){
          $noKontrol = $noKontrol;
        }
      }
      return $noKontrol;
      //exit();
    }

    public function getDisplayCheckin($referensi)
    {
      $title = 'Display Antrian Poliklinik';
      $logo  = $this->settings->get('settings.logo');

      $_username = '';
      $__username = 'Tamu';
      if(isset($_SESSION['mlite_user'])) {
        $_username = $this->core->getUserInfo('fullname', null, true);
        $__username = $this->core->getUserInfo('username');
      }
      $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
      $username      = !empty($_username) ? $_username : $__username;

      //echo 'Checkin';
      $referensi = $this->core->mysql('mlite_antrian_referensi')->where('nomor_referensi', $referensi)->oneArray();
      $pasien = $this->core->mysql('pasien')->where('no_peserta', $referensi['nomor_kartu'])->oneArray();
      $no_peserta = $referensi['nomor_kartu'];
      $no_rkm_medis = $pasien['no_rkm_medis'];
      $tgl_periksa = $referensi['tanggal_periksa'];
      $booking_registrasi = $this->core->mysql('booking_registrasi')->where('tanggal_periksa', date('Y-m-d'))->where('no_rkm_medis', $no_rkm_medis)->oneArray();
      $reg_periksa = $this->core->mysql('reg_periksa')->where('tgl_registrasi', date('Y-m-d'))->where('no_rkm_medis', $no_rkm_medis)->oneArray();
      if($booking_registrasi['status'] == 'Terdaftar') {
        $this->notify('failure', 'Anda sudah terdaftar!. Halaman akan beralih dalam 3 detik.');
        $redirect = url().'/anjungan/sep';
      } elseif($booking_registrasi['tanggal_periksa'] != date('Y-m-d')) {
        $this->notify('failure', 'Anda tidak bisa checkin hari ini. Booking anda terdaftar untuk tanggal '.$tgl_periksa.'.  Halaman akan beralih dalam 3 detik.');
        $redirect = url().'/anjungan/sep';
      } else {
        // Insert ke tabel reg_periksa //
        $cek_stts_daftar = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $no_rkm_medis)->count();
        $_POST['stts_daftar'] = 'Baru';
        if($cek_stts_daftar > 0) {
          $_POST['stts_daftar'] = 'Lama';
        }

        $biaya_reg = $this->core->mysql('poliklinik')->where('kd_poli', $booking_registrasi['kd_poli'])->oneArray();
        $_POST['biaya_reg'] = $biaya_reg['registrasi'];
        if($_POST['stts_daftar'] == 'Lama') {
          $_POST['biaya_reg'] = $biaya_reg['registrasilama'];
        }

        $cek_status_poli = $this->core->mysql('reg_periksa')->where('no_rkm_medis', $no_rkm_medis)->where('kd_poli', $booking_registrasi['kd_poli'])->count();
        $_POST['status_poli'] = 'Baru';
        if($cek_status_poli > 0) {
          $_POST['status_poli'] = 'Lama';
        }

        // set umur
        $date = new \DateTime($this->core->getPasienInfo('tgl_lahir', $no_rkm_medis));
        $today = new \DateTime(date('Y-m-d'));
        $y = $today->diff($date)->y;
        $m = $today->diff($date)->m;
        $d = $today->diff($date)->d;

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

        if($booking_registrasi['status'] == 'Belum') {
          $insert = $this->core->mysql('reg_periksa')
            ->save([
              'no_reg' => $booking_registrasi['no_reg'],
              'no_rawat' => $this->core->setNoRawat(date('Y-m-d')),
              'tgl_registrasi' => date('Y-m-d'),
              'jam_reg' => date('H:i:s'),
              'kd_dokter' => $booking_registrasi['kd_dokter'],
              'no_rkm_medis' => $no_rkm_medis,
              'kd_poli' => $booking_registrasi['kd_poli'],
              'p_jawab' => $this->core->getPasienInfo('namakeluarga', $no_rkm_medis),
              'almt_pj' => $this->core->getPasienInfo('alamatpj', $no_rkm_medis),
              'hubunganpj' => $this->core->getPasienInfo('keluarga', $no_rkm_medis),
              'biaya_reg' => $_POST['biaya_reg'],
              'stts' => 'Belum',
              'stts_daftar' => $_POST['stts_daftar'],
              'status_lanjut' => 'Ralan',
              'kd_pj' => $booking_registrasi['kd_pj'],
              'umurdaftar' => $umur,
              'sttsumur' => $sttsumur,
              'status_bayar' => 'Belum Bayar',
              'status_poli' => $_POST['status_poli']
            ]);

            if ($insert) {
                $this->core->mysql('booking_registrasi')->where('no_rkm_medis', $no_rkm_medis)->where('tanggal_periksa', date('Y-m-d'))->update('status', 'Terdaftar');
                $this->notify('success', 'Anda berhasil checkin untuk pelayanan hari ini. Halaman akan beralih dalam 3 detik untuk proses pencetakan SEP.');
                $redirect = url().'/anjungan/sep/'.$no_peserta.'/'.$no_rkm_medis;
            } else {
                $this->notify('failure', 'Checkin gagal');
                $redirect = url().'/anjungan/sep';
            }
        }
      }

      $content = $this->draw('checkin.html', [
        'title' => $title,
        'notify' => $this->core->getNotify(),
        'logo' => $logo,
        'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
        'username' => $username,
        'tanggal' => $tanggal,
        'running_text' => $this->settings->get('anjungan.text_poli'),
        'redirect' => $redirect
      ]);

      $assign = [
          'title' => $this->settings->get('settings.nama_instansi'),
          'desc' => $this->settings->get('settings.alamat'),
          'content' => $content
      ];

      $this->setTemplate("canvas.html");

      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function getDaftarBPJS($nik)
    {
      $title = 'Display Antrian Poliklinik';
      $logo  = $this->settings->get('settings.logo');

      $_username = '';
      $__username = 'Tamu';
      if(isset($_SESSION['mlite_user'])) {
        $_username = $this->core->getUserInfo('fullname', null, true);
        $__username = $this->core->getUserInfo('username');
      }
      $tanggal       = getDayIndonesia(date('Y-m-d')).', '.dateIndonesia(date('Y-m-d'));
      $username      = !empty($_username) ? $_username : $__username;

      $pasien = $this->core->mysql('pasien')->where('no_ktp', $nik)->oneArray();
      $no_peserta = $pasien['no_peserta'];
      $no_rkm_medis = $pasien['no_rkm_medis'];
      if($pasien) {
        $this->notify('failure', 'Anda sudah terdaftar sebagai pasien. Halaman akan beralih dalam 3 detik.');
        $redirect = url().'/anjungan/sep/'.$no_peserta.'/'.$no_rkm_medis;
      } else if(!$pasien) {
        date_default_timezone_set('UTC');
        $tStamp = strval(time() - strtotime("1970-01-01 00:00:00"));
        $key = $this->consid . $this->secretkey . $tStamp;
        $tglPelayananSEP = date('Y-m-d');

        $url = $this->api_url . 'Peserta/nik/' . $nik . '/tglSEP/' . $tglPelayananSEP;
        $output = BpjsService::get($url, NULL, $this->consid, $this->secretkey, $this->user_key, $tStamp);
        $json = json_decode($output, true);
        //echo json_encode($json);
        $code = $json['metaData']['code'];
        $message = $json['metaData']['message'];
        $stringDecrypt = stringDecrypt($key, $json['response']);
        $decompress = '""';
        if (!empty($stringDecrypt)) {
          $decompress = decompress($stringDecrypt);
        }
        if ($json != null) {
          $data = '{
            "metaData": {
              "code": "'.$code.'",
              "message": "'.$message.'"
            },
            "response": '.$decompress.'}';

          $data = json_decode($data, true);

          $no_rkm_medis = $this->core->setNoRM();
          $nm_pasien = $data['response']['peserta']['nama'];
          $nik = $data['response']['peserta']['nik'];
          $noKartu = $data['response']['peserta']['noKartu'];
          $sex = $data['response']['peserta']['sex'];
          $tglLahir = $data['response']['peserta']['tglLahir'];
          $umurPasien = $this->hitungUmur($tglLahir);

        } else {
          $this->notify('failure', 'Pendaftaran periksa gagal. Silahkan coba lagi. Halaman akan beralih dalam 3 detik.');
          $redirect = url().'/anjungan/sep';
        }

        $simpanPasien = $this->core->mysql('pasien')->save([
          'no_rkm_medis' => $no_rkm_medis,
          'nm_pasien' => $nm_pasien,
          'no_ktp' => $nik,
          'jk' => $sex,
          'tmp_lahir' => '-',
          'tgl_lahir' => $tglLahir,
          'nm_ibu' => '-',
          'alamat' => '-',
          'gol_darah' => '-',
          'pekerjaan' => '-',
          'stts_nikah' => 'BELUM MENIKAH',
          'agama' => 'ISLAM',
          'tgl_daftar' => date('Y-m-d'),
          'no_tlp' => '000000000000',
          'umur' => $umurPasien,
          'pnd' => '-',
          'keluarga' => 'SAUDARA',
          'namakeluarga' => '',
          'kd_pj' => 'BPJ',
          'no_peserta' => $noKartu,
          'kd_kel' => '1',
          'kd_kec' => '1',
          'kd_kab' => '1',
          'pekerjaanpj' => '',
          'alamatpj' => 'ALAMAT',
          'kelurahanpj' => 'KELURAHAN',
          'kecamatanpj' => 'KECAMATAN',
          'kabupatenpj' => 'KABUPATEN',
          'perusahaan_pasien' => '-',
          'suku_bangsa' => '1',
          'bahasa_pasien' => '1',
          'cacat_fisik' => '1',
          'email' => '',
          'nip' => '',
          'kd_prop' => '1',
          'propinsipj' => 'PROPINSI'
        ]);
        if($this->core->mysql('pasien')->where('no_ktp', $nik)->oneArray()) {
          $this->notify('success', 'Pendaftaran periksa sukses. Halaman akan beralih dalam 3 detik.');
          $redirect = url().'/anjungan/sep/'.$noKartu.'/'.$no_rkm_medis;
        }
      } else {
        $this->notify('failure', 'Ada kegagalan sistem. Halaman akan beralih dalam 3 detik.');
        $redirect = url().'/anjungan/sep';
      }

      $content = $this->draw('daftar.html', [
        'title' => $title,
        'notify' => $this->core->getNotify(),
        'logo' => $logo,
        'powered' => 'Powered by <a href="https://mlite.id/">mLITE</a>',
        'username' => $username,
        'tanggal' => $tanggal,
        'running_text' => $this->settings->get('anjungan.text_poli'),
        'redirect' => $redirect
      ]);

      $assign = [
          'title' => $this->settings->get('settings.nama_instansi'),
          'desc' => $this->settings->get('settings.alamat'),
          'content' => $content
      ];

      $this->setTemplate("canvas.html");

      $this->tpl->set('page', ['title' => $assign['title'], 'desc' => $assign['desc'], 'content' => $assign['content']]);

    }

    public function hitungUmur($tanggal_lahir)
    {
      $birthDate = new \DateTime($tanggal_lahir);
      $today = new \DateTime("today");
      $umur = "0 Th 0 Bl 0 Hr";
      if ($birthDate < $today) {
        $y = $today->diff($birthDate)->y;
        $m = $today->diff($birthDate)->m;
        $d = $today->diff($birthDate)->d;
        $umur =  $y." Th ".$m." Bl ".$d." Hr";
      }
      return $umur;
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        echo $this->draw(MODULES.'/anjungan/js/antrian.js');
        exit();
    }

}
