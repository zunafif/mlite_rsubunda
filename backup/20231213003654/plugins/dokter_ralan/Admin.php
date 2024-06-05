<?php
namespace Plugins\Dokter_Ralan;

use Systems\AdminModule;
use Plugins\Icd\DB_ICD;

class Admin extends AdminModule
{

    private $_uploads = WEBAPPS_PATH.'/berkasrawat/pages/upload';
    public function navigation()
    {
        return [
            'Kelola'   => 'index',
            'Dokter Ralan'   => 'manage',
            'Pengaturan' =>'settings'
        ];
    }

    public function getIndex()
    {
      $sub_modules = [
        ['name' => 'Dokter Ralan', 'url' => url([ADMIN, 'dokter_ralan', 'manage']), 'icon' => 'wheelchair', 'desc' => 'Data pasien rawat jalan'],
        ['name' => 'Pengaturan', 'url' => url([ADMIN, 'dokter_ralan', 'settings']), 'icon' => 'wrench', 'desc' => 'Pengaturan dokter rawat jalan'],
      ];
      return $this->draw('index.html', ['sub_modules' => $sub_modules]);
    }

    public function anyManage()
    {
        $tgl_kunjungan = date('Y-m-d');
        $tgl_kunjungan_akhir = date('Y-m-d');
        $status_periksa = '';

        if(isset($_POST['periode_rawat_jalan'])) {
          $tgl_kunjungan = $_POST['periode_rawat_jalan'];
        }
        if(isset($_POST['periode_rawat_jalan_akhir'])) {
          $tgl_kunjungan_akhir = $_POST['periode_rawat_jalan_akhir'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $responsivevoice =  $this->settings->get('settings.responsivevoice');
        $this->_Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa);
        return $this->draw('manage.html', ['rawat_jalan' => $this->assign, 'cek_vclaim' => $cek_vclaim, 'responsivevoice' => $responsivevoice, 'admin_mode' => $this->settings->get('settings.admin_mode')]);
    }

    public function anyDisplay()
    {
        $tgl_kunjungan = date('Y-m-d');
        $tgl_kunjungan_akhir = date('Y-m-d');
        $status_periksa = '';

        if(isset($_POST['periode_rawat_jalan'])) {
          $tgl_kunjungan = $_POST['periode_rawat_jalan'];
        }
        if(isset($_POST['periode_rawat_jalan_akhir'])) {
          $tgl_kunjungan_akhir = $_POST['periode_rawat_jalan_akhir'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $responsivevoice =  $this->settings->get('settings.responsivevoice');
        $this->_Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa);
        echo $this->draw('display.html', ['rawat_jalan' => $this->assign, 'cek_vclaim' => $cek_vclaim, 'responsivevoice' => $responsivevoice, 'admin_mode' => $this->settings->get('settings.admin_mode')]);
        exit();
    }

    public function _Display($tgl_kunjungan, $tgl_kunjungan_akhir, $status_periksa='')
    {
        if($this->settings->get('settings.responsivevoice') == 'true') {
          $this->core->addJS(url('assets/jscripts/responsivevoice.js'));
        }
        $this->_addHeaderFiles();
        $username = $this->core->getUserInfo('username', null, true);
        $this->assign['poliklinik']     = $this->core->mysql('poliklinik')->where('status', '1')->toArray();
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
          if($this->settings->get('settings.dokter_ralan_per_dokter') == 'true') {
            $sql .= " AND reg_periksa.kd_dokter = '$username'";
          } else {
            $sql .= " AND reg_periksa.kd_poli IN ('$poliklinik')";
          }
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
          $this->assign['list'][] = $row;
        }

    }

    public function postSaveDetail()
    {
      if($_POST['kat'] == 'tindakan') {
        $jns_perawatan = $this->core->mysql('jns_perawatan')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->oneArray();
        $this->core->mysql('rawat_jl_dr')->save([
          'no_rawat' => $_POST['no_rawat'],
          'kd_jenis_prw' => $_POST['kd_jenis_prw'],
          'kd_dokter' => $this->core->getUserInfo('username', null, true),
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

      if($_POST['kat'] == 'obat') {

        $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);
        $cek_resep = $this->core->mysql('resep_obat')->where('no_rawat', $_POST['no_rawat'])->where('tgl_peresepan', $_POST['tgl_perawatan'])->where('tgl_perawatan', '0000-00-00')->oneArray();

        if(empty($cek_resep)) {

            $resep_obat = $this->core->mysql('resep_obat')
              ->save([
                'no_resep' => $no_resep,
                'tgl_perawatan' => '0000-00-00',
                'jam' => '00:00:00',
                'no_rawat' => $_POST['no_rawat'],
                'kd_dokter' => $this->core->getUserInfo('username', null, true),
                'tgl_peresepan' => $_POST['tgl_perawatan'],
                'jam_peresepan' => $_POST['jam_rawat'],
                'status' => 'ralan',
                'tgl_penyerahan' => '0000-00-00',
                'jam_penyerahan' => '00:00:00'
              ]);

            if ($this->core->mysql('resep_obat')->where('no_resep', $no_resep)->where('kd_dokter', $this->core->getUserInfo('username', null, true))->oneArray()) {
              $this->core->mysql('resep_dokter')
                ->save([
                  'no_resep' => $no_resep,
                  'kode_brng' => $_POST['kd_jenis_prw'],
                  'jml' => $_POST['jml'],
                  'aturan_pakai' => $_POST['aturan_pakai']
                ]);
            }

        } else {

          $no_resep = $cek_resep['no_resep'];
          $this->core->mysql('resep_dokter')
            ->save([
              'no_resep' => $no_resep,
              'kode_brng' => $_POST['kd_jenis_prw'],
              'jml' => $_POST['jml'],
              'aturan_pakai' => $_POST['aturan_pakai']
            ]);

        }

      }

      if($_POST['kat'] == 'racikan') {

        $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);
        $cek_resep = $this->core->mysql('resep_obat')->where('no_rawat', $_POST['no_rawat'])->where('tgl_peresepan', $_POST['tgl_perawatan'])->where('tgl_perawatan', '0000-00-00')->oneArray();

        $_POST['jam_rawat'] = date('H:i:s');

        if(empty($cek_resep)) {

          $resep_obat = $this->core->mysql('resep_obat')
            ->save([
              'no_resep' => $no_resep,
              'tgl_perawatan' => '0000-00-00',
              'jam' => '00:00:00',
              'no_rawat' => $_POST['no_rawat'],
              'kd_dokter' => $this->core->getUserInfo('username', null, true),
              'tgl_peresepan' => $_POST['tgl_perawatan'],
              'jam_peresepan' => $_POST['jam_rawat'],
              'status' => 'ralan',
              'tgl_penyerahan' => '0000-00-00',
              'jam_penyerahan' => '00:00:00'
            ]);

          if ($this->core->mysql('resep_obat')->where('no_resep', $no_resep)->where('kd_dokter', $this->core->getUserInfo('username', null, true))->oneArray()) {
            $no_racik = $this->core->mysql('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
            $no_racik = $no_racik+1;
            $this->core->mysql('resep_dokter_racikan')
              ->save([
                'no_resep' => $no_resep,
                'no_racik' => $no_racik,
                'nama_racik' => $_POST['nama_racik'],
                'kd_racik' => $_POST['kd_jenis_prw'],
                'jml_dr' => $_POST['jml'],
                'aturan_pakai' => $_POST['aturan_pakai'],
                'keterangan' => $_POST['keterangan']
              ]);
            $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
            $_POST['kandungan'] = json_decode($_POST['kandungan'], true);
            for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
              $kapasitas = $this->core->mysql('databarang')->where('kode_brng', $_POST['kode_brng'][$i]['value'])->oneArray();
              $jml = $_POST['jml']*$_POST['kandungan'][$i]['value'];
              $jml = round(($jml/$kapasitas['kapasitas']),1);
              $this->core->mysql('resep_dokter_racikan_detail')
                ->save([
                  'no_resep' => $no_resep,
                  'no_racik' => $no_racik,
                  'kode_brng' => $_POST['kode_brng'][$i]['value'],
                  'p1' => '1',
                  'p2' => '1',
                  'kandungan' => $_POST['kandungan'][$i]['value'],
                  'jml' => $jml
                ]);
            }
          }

        } else {

          $no_resep = $cek_resep['no_resep'];

          $no_racik = $this->core->mysql('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
          $no_racik = $no_racik+1;
          $this->core->mysql('resep_dokter_racikan')
            ->save([
              'no_resep' => $no_resep,
              'no_racik' => $no_racik,
              'nama_racik' => $_POST['nama_racik'],
              'kd_racik' => $_POST['kd_jenis_prw'],
              'jml_dr' => $_POST['jml'],
              'aturan_pakai' => $_POST['aturan_pakai'],
              'keterangan' => $_POST['keterangan']
            ]);
          $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
          $_POST['kandungan'] = json_decode($_POST['kandungan'], true);
          for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
            $kapasitas = $this->core->mysql('databarang')->where('kode_brng', $_POST['kode_brng'][$i]['value'])->oneArray();
            $jml = $_POST['jml']*$_POST['kandungan'][$i]['value'];
            $jml = round(($jml/$kapasitas['kapasitas']),1);
            $this->core->mysql('resep_dokter_racikan_detail')
              ->save([
                'no_resep' => $no_resep,
                'no_racik' => $no_racik,
                'kode_brng' => $_POST['kode_brng'][$i]['value'],
                'p1' => '1',
                'p2' => '1',
                'kandungan' => $_POST['kandungan'][$i]['value'],
                'jml' => $jml
              ]);
          }

        }

      }

      if($_POST['kat'] == 'laboratorium') {
        $cek_lab = $this->core->mysql('permintaan_lab')->where('no_rawat', $_POST['no_rawat'])->where('tgl_permintaan', date('Y-m-d'))->where('tgl_sampel', '0000-00-00')->where('status', 'ralan')->oneArray();
        if(!$cek_lab) {
          $max_id = $this->core->mysql('permintaan_lab')->select(['noorder' => 'ifnull(MAX(CONVERT(RIGHT(noorder,4),signed)),0)'])->where('tgl_permintaan', date('Y-m-d'))->oneArray();
          if(empty($max_id['noorder'])) {
            $max_id['noorder'] = '0000';
          }
          $_next_noorder = sprintf('%04s', ($max_id['noorder'] + 1));
          $noorder = 'PL'.date('Ymd').''.$_next_noorder;

          $permintaan_lab = $this->core->mysql('permintaan_lab')
            ->save([
              'noorder' => $noorder,
              'no_rawat' => $_POST['no_rawat'],
              'tgl_permintaan' => $_POST['tgl_perawatan'],
              'jam_permintaan' => $_POST['jam_rawat'],
              'tgl_sampel' => '0000-00-00',
              'jam_sampel' => '00:00:00',
              'tgl_hasil' => '0000-00-00',
              'jam_hasil' => '00:00:00',
              'dokter_perujuk' => $this->core->getUserInfo('username', null, true),
              'status' => 'ralan',
              'informasi_tambahan' => $_POST['informasi_tambahan'],
              'diagnosa_klinis' => $_POST['diagnosa_klinis']
            ]);
          $this->core->mysql('permintaan_pemeriksaan_lab')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
          $template_laboratorium = $this->core->mysql('template_laboratorium')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->toArray();
          for ($i = 0; $i < count($template_laboratorium); $i++) {
            $this->core->mysql('permintaan_detail_permintaan_lab')
              ->save([
                'noorder' => $noorder,
                'kd_jenis_prw' => $_POST['kd_jenis_prw'],
                'id_template' => $template_laboratorium[$i]['id_template'],
                'stts_bayar' => 'Belum'
              ]);
          }
        } else {
          $noorder = $cek_lab['noorder'];
          $this->core->mysql('permintaan_pemeriksaan_lab')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
          $template_laboratorium = $this->core->mysql('template_laboratorium')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->toArray();
          for ($i = 0; $i < count($template_laboratorium); $i++) {
            $this->core->mysql('permintaan_detail_permintaan_lab')
              ->save([
                'noorder' => $noorder,
                'kd_jenis_prw' => $_POST['kd_jenis_prw'],
                'id_template' => $template_laboratorium[$i]['id_template'],
                'stts_bayar' => 'Belum'
              ]);
          }
        }
      }

      if($_POST['kat'] == 'radiologi') {
        $cek_rad = $this->core->mysql('permintaan_radiologi')->where('no_rawat', $_POST['no_rawat'])->where('tgl_permintaan', date('Y-m-d'))->where('tgl_sampel', '<>', '0000-00-00')->where('status', 'ralan')->oneArray();
        if(!$cek_rad) {
          $max_id = $this->core->mysql('permintaan_radiologi')->select(['noorder' => 'ifnull(MAX(CONVERT(RIGHT(noorder,4),signed)),0)'])->where('tgl_permintaan', date('Y-m-d'))->oneArray();
          if(empty($max_id['noorder'])) {
            $max_id['noorder'] = '0000';
          }
          $_next_noorder = sprintf('%04s', ($max_id['noorder'] + 1));
          $noorder = 'PR'.date('Ymd').''.$_next_noorder;

          $permintaan_rad = $this->core->mysql('permintaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'no_rawat' => $_POST['no_rawat'],
              'tgl_permintaan' => $_POST['tgl_perawatan'],
              'jam_permintaan' => $_POST['jam_rawat'],
              'tgl_sampel' => '0000-00-00',
              'jam_sampel' => '00:00:00',
              'tgl_hasil' => '0000-00-00',
              'jam_hasil' => '00:00:00',
              'dokter_perujuk' => $this->core->getUserInfo('username', null, true),
              'status' => 'ralan',
              'informasi_tambahan' => $_POST['informasi_tambahan'],
              'diagnosa_klinis' => $_POST['diagnosa_klinis']
            ]);
          $this->core->mysql('permintaan_pemeriksaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);

        } else {
          $noorder = $cek_rad['noorder'];
          $this->core->mysql('permintaan_pemeriksaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
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

    public function postHapusPermintaanLab()
    {
      $this->core->mysql('permintaan_lab')
      ->where('noorder', $_POST['noorder'])
      ->where('no_rawat', $_POST['no_rawat'])
      ->delete();
      exit();
    }

    public function postHapusPermintaanRad()
    {
      $this->core->mysql('permintaan_radiologi')
      ->where('noorder', $_POST['noorder'])
      ->where('no_rawat', $_POST['no_rawat'])
      ->delete();
      exit();
    }

    public function postHapusResep()
    {
      if(isset($_POST['kd_jenis_prw'])) {
        $this->core->mysql('resep_dokter')
        ->where('no_resep', $_POST['no_resep'])
        ->where('kode_brng', $_POST['kd_jenis_prw'])
        ->delete();
      } else {
        $this->core->mysql('resep_obat')
        ->where('no_resep', $_POST['no_resep'])
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('tgl_peresepan', $_POST['tgl_peresepan'])
        ->where('jam_peresepan', $_POST['jam_peresepan'])
        ->delete();
      }

      exit();
    }

    public function anyCopyResep()
    {
      $return = $this->core->mysql('resep_dokter')
        ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
        ->join('gudangbarang', 'gudangbarang.kode_brng=resep_dokter.kode_brng')
        ->where('kd_bangsal', $this->settings->get('farmasi.deporalan'))
        ->where('no_resep', $_POST['no_resep'])
        ->toArray();
      echo $this->draw('copyresep.display.html', ['copy_resep' => $return]);
      exit();
    }

    public function postSaveCopyResep()
    {
      $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
      $_POST['jml'] = json_decode($_POST['jml'], true);
      $_POST['aturan_pakai'] = json_decode($_POST['aturan_pakai'], true);

      $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);

      $resep_obat = $this->core->mysql('resep_obat')
        ->save([
          'no_resep' => $no_resep,
          'tgl_perawatan' => '0000-00-00',
          'jam' => '00:00:00',
          'no_rawat' => $_POST['no_rawat'],
          'kd_dokter' => $this->core->getUserInfo('username', null, true),
          'tgl_peresepan' => $_POST['tgl_perawatan'],
          'jam_peresepan' => $_POST['jam_rawat'],
          'status' => 'ralan',
          'tgl_penyerahan' => '0000-00-00',
          'jam_penyerahan' => '00:00:00'
        ]);

      for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
          $this->core->mysql('resep_dokter')
            ->save([
              'no_resep' => $no_resep,
              'kode_brng' => $_POST['kode_brng'][$i]['value'],
              'jml' => $_POST['jml'][$i]['value'],
              'aturan_pakai' => $_POST['aturan_pakai'][$i]['value']
            ]);

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

      $rows = $this->core->mysql('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('resep_obat.status', 'ralan')
        ->group('resep_dokter.no_resep')
        ->toArray();
      $resep = [];
      $jumlah_total_resep = 0;
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter'] = $this->core->mysql('resep_dokter')->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep += floatval($value['ralan']);
        }
        $resep[] = $row;
      }

      $rows_racikan = $this->core->mysql('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->group('resep_dokter_racikan.no_resep')
        ->where('resep_obat.status', 'ralan')
        ->toArray();
      $resep_racikan = [];
      $jumlah_total_resep_racikan = 0;
      foreach ($rows_racikan as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter_racikan_detail'] = $this->core->mysql('resep_dokter_racikan_detail')->join('databarang', 'databarang.kode_brng=resep_dokter_racikan_detail.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter_racikan_detail'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep_racikan += floatval($value['ralan']);
        }
        $resep_racikan[] = $row;
      }

      /*
      $rows_laboratorium = $this->core->mysql('permintaan_lab')->join('permintaan_pemeriksaan_lab', 'permintaan_pemeriksaan_lab.noorder=permintaan_lab.noorder')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $jumlah_total_lab = 0;
      $laboratorium = [];

      if($rows_laboratorium) {
        foreach ($rows_laboratorium as $row) {
          $jns_perawatan = $this->core->mysql('jns_perawatan_lab')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $row['kelas'] = $jns_perawatan['kelas'];
          $row['total_byr'] = $jns_perawatan['total_byr'];
          $jumlah_total_lab += $jns_perawatan['total_byr'];
          $laboratorium[] = $row;
        }
      }
      */

      $rows_laboratorium = $this->core->mysql('permintaan_lab')
        ->join('dokter', 'dokter.kd_dokter=permintaan_lab.dokter_perujuk')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('permintaan_lab.status', 'ralan')
        ->toArray();
      $laboratorium = [];
      foreach ($rows_laboratorium as $row) {
        $rows2 = $this->core->mysql('permintaan_pemeriksaan_lab')
          ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=permintaan_pemeriksaan_lab.kd_jenis_prw')
          //->join('permintaan_detail_permintaan_lab', 'permintaan_detail_permintaan_lab.noorder=permintaan_pemeriksaan_lab.noorder')
          ->where('permintaan_pemeriksaan_lab.noorder', $row['noorder'])
          ->toArray();
          $row['permintaan_pemeriksaan_lab'] = [];
          foreach ($rows2 as $row2) {
            $row2['noorder'] = $row2['noorder'];
            $row2['kd_jenis_prw'] = $row2['kd_jenis_prw'];
            $row2['stts_bayar'] = $row2['stts_bayar'];
            $row2['nm_perawatan'] = $row2['nm_perawatan'];
            $row2['kd_pj'] = $row2['kd_pj'];
            $row2['status'] = $row2['status'];
            $row2['kelas'] = $row2['kelas'];
            $row2['kategori'] = $row2['kategori'];
            $rows3 = $this->core->mysql('permintaan_detail_permintaan_lab')->where('noorder', $row2['noorder'])->where('kd_jenis_prw', $row2['kd_jenis_prw'])->toArray();
            $row2['permintaan_detail_permintaan_lab'] = [];
            foreach ($rows3 as $row3) {
              $row3['template_laboratorium'] = $this->core->mysql('template_laboratorium')->where('kd_jenis_prw', $row3['kd_jenis_prw'])->where('id_template', $row3['id_template'])->oneArray();
              $row2['permintaan_detail_permintaan_lab'][] = $row3;
            }
            $row['permintaan_pemeriksaan_lab'][] = $row2;
          }
        $laboratorium[] = $row;
      }

      $rows_radiologi = $this->core->mysql('permintaan_radiologi')
        ->join('permintaan_pemeriksaan_radiologi', 'permintaan_pemeriksaan_radiologi.noorder=permintaan_radiologi.noorder')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('permintaan_radiologi.status', 'ralan')
        ->toArray();
      $jumlah_total_rad = 0;
      $radiologi = [];

      if($rows_radiologi) {
        foreach ($rows_radiologi as $row) {
          $jns_perawatan = $this->core->mysql('jns_perawatan_radiologi')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $row['kelas'] = $jns_perawatan['kelas'];
          $row['total_byr'] = $jns_perawatan['total_byr'];
          $jumlah_total_rad += $jns_perawatan['total_byr'];
          $radiologi[] = $row;
        }
      }

      $reg_periksa = $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
      $rows_data_resep = $this->core->mysql('resep_obat')
      ->join('reg_periksa', 'reg_periksa.no_rawat=resep_obat.no_rawat')
      ->where('resep_obat.kd_dokter', $this->core->getUserInfo('username', null, true))
      ->where('reg_periksa.no_rkm_medis', $reg_periksa['no_rkm_medis'])
      ->toArray();

      $data_resep = [];
      foreach ($rows_data_resep as $row) {
        $row['resep_dokter'] = $this->core->mysql('resep_dokter')
          ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
          ->where('no_resep', $row['no_resep'])
          ->toArray();
        $data_resep[] = $row;
      }

      echo $this->draw('rincian.html', [
        'rawat_jl_dr' => $rawat_jl_dr,
        'rawat_jl_pr' => $rawat_jl_pr,
        'rawat_jl_drpr' => $rawat_jl_drpr,
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'laboratorium' => $laboratorium,
        'radiologi' => $radiologi,
        'jumlah_total' => $jumlah_total,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        //'jumlah_total_lab' => $jumlah_total_lab,
        'jumlah_total_rad' => $jumlah_total_rad,
        'no_rawat' => $_POST['no_rawat']
      ]);
      exit();
    }

    public function anyRincianEresep()
    {
      $i = 1;

      $rows = $this->core->mysql('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('resep_obat.status', 'ralan')
        ->group('resep_dokter.no_resep')
        ->toArray();
      $resep = [];
      $jumlah_total_resep = 0;
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter'] = $this->core->mysql('resep_dokter')->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep += floatval($value['ralan']);
        }
        $resep[] = $row;
      }

      $rows_racikan = $this->core->mysql('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->group('resep_dokter_racikan.no_resep')
        ->where('resep_obat.status', 'ralan')
        ->toArray();
      $resep_racikan = [];
      $jumlah_total_resep_racikan = 0;
      foreach ($rows_racikan as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter_racikan_detail'] = $this->core->mysql('resep_dokter_racikan_detail')->join('databarang', 'databarang.kode_brng=resep_dokter_racikan_detail.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter_racikan_detail'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep_racikan += floatval($value['ralan']);
        }
        $resep_racikan[] = $row;
      }

      $reg_periksa = $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
      $rows_data_resep = $this->core->mysql('resep_obat')
      ->join('reg_periksa', 'reg_periksa.no_rawat=resep_obat.no_rawat')
      ->where('resep_obat.kd_dokter', $this->core->getUserInfo('username', null, true))
      ->where('reg_periksa.no_rkm_medis', $reg_periksa['no_rkm_medis'])
      ->toArray();

      $data_resep = [];
      foreach ($rows_data_resep as $row) {
        $row['resep_dokter'] = $this->core->mysql('resep_dokter')
          ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
          ->where('no_resep', $row['no_resep'])
          ->toArray();
        $data_resep[] = $row;
      }

      echo $this->draw('rincian.eresep.html', [
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        'no_rawat' => $_POST['no_rawat']
      ]);
      exit();
    }

    public function postHapusNomorPermintaanLaboratorium()
    {
      $this->core->mysql('permintaan_lab')
      ->where('no_rawat', $_POST['no_rawat'])
      ->where('noorder', $_POST['noorder'])
      ->where('tgl_permintaan', $_POST['tgl_permintaan'])
      ->where('jam_permintaan', $_POST['jam_permintaan'])
      ->where('status', 'Ralan')
      ->delete();
      exit();
    }

    public function postHapusPermintaanLaboratorium()
    {
      $this->core->mysql('permintaan_pemeriksaan_lab')
      ->where('noorder', $_POST['noorder'])
      ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
      ->where('stts_bayar', 'Belum')
      ->delete();
      exit();
    }

    public function getDetailPermintaan($noorder, $kd_jenis_prw)
    {
      $rows = $this->core->mysql('permintaan_detail_permintaan_lab')->where('noorder', $noorder)->where('kd_jenis_prw', $kd_jenis_prw)->toArray();
      $detail_permintaan_lab = [];
      foreach ($rows as $row) {
        $row['template_laboratorium'] = $this->core->mysql('template_laboratorium')->where('kd_jenis_prw', $row['kd_jenis_prw'])->where('id_template', $row['id_template'])->oneArray();
        $detail_permintaan_lab[] = $row;
      }
      $this->tpl->set('detail', $detail_permintaan_lab);
      echo $this->tpl->draw(MODULES.'/dokter_ralan/view/admin/details.html', true);
      exit();
    }

    public function postHapusDetailPermintaan()
    {
      $this->core->mysql('permintaan_detail_permintaan_lab')
        ->where('noorder', $_POST['noorder'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('id_template', $_POST['id_template'])
        ->delete();
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

      $i = 1;
      $row['nama_petugas'] = '';
      $row['departemen_petugas'] = '';
      $rows = $this->core->mysql('pemeriksaan_ralan')
        ->where('no_rawat', $_POST['no_rawat'])
        ->toArray();
      $result = [];
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
        $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
        $result[] = $row;
      }

      $result_ranap = [];
      if($this->db('mlite_settings')->where('module', 'rawat_inap')->oneArray()) {
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
        if($this->settings->get('dokter_ralan.set_sudah') == 'ya') {
          $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->save(['stts' => 'Sudah']);
        }
      } else {
        $this->core->mysql('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->save($_POST);
        if($this->settings->get('dokter_ralan.set_sudah') == 'ya') {
          $this->core->mysql('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->save(['stts' => 'Sudah']);
        }
      }
      exit();
    }

    public function postHapusSOAP()
    {
      $this->core->mysql('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->delete();
      exit();
    }

    public function anyKontrol()
    {
      $rows = $this->core->mysql('booking_registrasi')
        ->select([
          'tanggal_periksa' => 'booking_registrasi.tanggal_periksa',
          'no_reg' => 'booking_registrasi.no_reg',
          'nm_poli' => 'poliklinik.nm_poli',
          'nm_dokter' => 'dokter.nm_dokter',
          'png_jawab' => 'penjab.png_jawab',
          'status' => 'booking_registrasi.status'
        ])
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
        'kd_dokter' => $this->core->getUserInfo('username', null, true),
        'status' => 'Menunggu'
      ]);

      if ($query) {
        $this->core->mysql('booking_registrasi')
          ->save([
            'tanggal_booking' => date('Y-m-d'),
            'jam_booking' => date('H:i:s'),
            'no_rkm_medis' => $_POST['no_rkm_medis'],
            'tanggal_periksa' => $_POST['tanggal_datang'],
            'kd_dokter' => $this->core->getUserInfo('username', null, true),
            'kd_poli' => $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']),
            'no_reg' => $this->core->setNoBooking($this->core->getUserInfo('username', null, true), $this->core->getRegPeriksaInfo('kd_poli', $no_rawat), $_POST['tanggal_datang']),
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
      $maping_poli_bpjs = $this->core->mysql('maping_poli_bpjs')->where('kd_poli', $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']))->oneArray();
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
      $this->core->mysql('pemeriksaan_ralan')->where('no_rawat', $_POST['no_rawat'])->delete();
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

    public function anyObat()
    {
      $obat = $this->core->mysql('databarang')
        ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
        ->where('status', '1')
        ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporalan'))
        ->like('databarang.nama_brng', '%'.$_POST['obat'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('obat.html', ['obat' => $obat]);
      exit();
    }

    public function anyObatRacikan()
    {
      $obat = $this->core->mysql('databarang')
        ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
        ->where('status', '1')
        ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporalan'))
        ->like('databarang.nama_brng', '%'.$_POST['obat'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('obat.racikan.html', ['obat' => $obat]);
      exit();
    }

    public function anyRacikan()
    {
      $racikan = $this->core->mysql('metode_racik')
        ->like('nm_racik', '%'.$_POST['racikan'].'%')
        ->toArray();
      echo $this->draw('racikan.html', ['racikan' => $racikan]);
      exit();
    }

    public function anyLaboratorium()
    {
      $laboratorium = $this->core->mysql('jns_perawatan_lab')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['laboratorium'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('laboratorium.html', ['laboratorium' => $laboratorium]);
      exit();
    }

    public function anyRadiologi()
    {
      $radiologi = $this->core->mysql('jns_perawatan_radiologi')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['radiologi'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('radiologi.html', ['radiologi' => $radiologi]);
      exit();
    }

    public function postAturanPakai()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->core->mysql('master_aturan_pakai')->like('aturan', $key)->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["aturan"].'</li>';
          }
        }
        echo $output;
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

    public function getAjax()
    {
        header('Content-type: text/html');
        $show = isset($_GET['show']) ? $_GET['show'] : "";
        switch($show){
        	default:
          break;
          case "databarang":
          $rows = $this->core->mysql('databarang')
            ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
            ->where('status', '1')
            ->where('stok', '>', '10')
            ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporalan'))
            ->like('databarang.nama_brng', '%'.$_GET['nama_brng'].'%')
            ->limit(10)
            ->toArray();

          foreach ($rows as $row) {
            $array[] = array(
                'kode_brng' => $row['kode_brng'],
                'nama_brng'  => $row['nama_brng']
            );
          }
          echo json_encode($array, true);
          break;
          case "aturan_pakai":
          $rows = $this->core->mysql('master_aturan_pakai')->like('aturan', '%'.$_GET['aturan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'aturan'  => $row['aturan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan":
          $rows = $this->core->mysql('jns_perawatan')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan_lab":
          $rows = $this->core->mysql('jns_perawatan_lab')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "jns_perawatan_radiologi":
          $rows = $this->core->mysql('jns_perawatan_radiologi')->like('nm_perawatan', '%'.$_GET['nm_perawatan'].'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nm_perawatan'  => $row['nm_perawatan']
            );
          }
          echo json_encode($array, true);
          break;
          case "icd10":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->data_icd('icd10')->like('kode', '%'.$phrase.'%')->orLike('nama', '%'.$phrase.'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kd_penyakit' => $row['kode'],
                'nm_penyakit'  => $row['nama']
            );
          }
          echo json_encode($array, true);
          break;
          case "icd9":
          $phrase = '';
          if(isset($_GET['s']))
            $phrase = $_GET['s'];

          $rows = $this->data_icd('icd9')->like('kode', '%'.$phrase.'%')->orLike('nama', '%'.$phrase.'%')->toArray();
          foreach ($rows as $row) {
            $array[] = array(
                'kode' => $row['kode'],
                'deskripsi_panjang'  => $row['nama']
            );
          }
          echo json_encode($array, true);
          break;
        }
        exit();
    }

    public function getEresep($no_rawat)
    {
      $no_rawat = revertNorawat($no_rawat);
      $i = 1;

      $rows = $this->core->mysql('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $no_rawat)
        ->where('resep_obat.status', 'ralan')
        ->group('resep_dokter.no_resep')
        ->toArray();
      $resep = [];
      $jumlah_total_resep = 0;
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter'] = $this->core->mysql('resep_dokter')->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep += floatval($value['ralan']);
        }
        $resep[] = $row;
      }

      $rows_racikan = $this->core->mysql('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $no_rawat)
        ->group('resep_dokter_racikan.no_resep')
        ->where('resep_obat.status', 'ralan')
        ->toArray();
      $resep_racikan = [];
      $jumlah_total_resep_racikan = 0;
      foreach ($rows_racikan as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter_racikan_detail'] = $this->core->mysql('resep_dokter_racikan_detail')->join('databarang', 'databarang.kode_brng=resep_dokter_racikan_detail.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter_racikan_detail'] as $value) {
          $value['ralan'] = $value['jml'] * $value['ralan'];
          $jumlah_total_resep_racikan += floatval($value['ralan']);
        }
        $resep_racikan[] = $row;
      }

      $reg_periksa = $this->core->mysql('reg_periksa')->where('no_rawat', $no_rawat)->oneArray();
      $rows_data_resep = $this->core->mysql('resep_obat')
      ->join('reg_periksa', 'reg_periksa.no_rawat=resep_obat.no_rawat')
      ->where('resep_obat.kd_dokter', $this->core->getUserInfo('username', null, true))
      ->where('reg_periksa.no_rkm_medis', $this->core->getRegPeriksaInfo('no_rkm_medis', $no_rawat))
      ->toArray();

      $data_resep = [];
      foreach ($rows_data_resep as $row) {
        $row['resep_dokter'] = $this->core->mysql('resep_dokter')
          ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
          ->where('no_resep', $row['no_resep'])
          ->toArray();
        $data_resep[] = $row;
      }

      echo $this->draw('eresep.html', [
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        'no_rawat' => $no_rawat
      ]);
      exit();
    }

    public function postCekWaktu()
    {
      echo date('H:i:s');
      exit();
    }

    public function getOdontogram($no_rkm_medis)
    {
      echo $this->draw('odontogram.html', ['odontogram' => $this->core->mysql('mlite_odontogram')->where('no_rkm_medis', $no_rkm_medis)->toArray()]);
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

    public function getResume($no_rawat)
    {
      $data_resume['pemeriksaan_ralan'] = $this->core->mysql('pemeriksaan_ralan')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
      $data_resume['diagnosa'] = $this->core->mysql('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('diagnosa_pasien.status', 'Ralan')->oneArray();
      $data_resume['prosedur'] = $this->core->mysql('prosedur_pasien')->join('icd9', 'icd9.kode=prosedur_pasien.kode')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('status', 'Ralan')->oneArray();
      echo $this->draw('resume.html', [
        'reg_periksa' => $this->core->mysql('reg_periksa')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'resume_pasien' => $this->core->mysql('resume_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'data_resume' => $data_resume
      ]);
      exit();
    }

    public function getResumeTampil($no_rawat)
    {
      echo $this->draw('resume.tampil.html', ['resume_pasien' => $this->core->mysql('resume_pasien')->where('no_rawat', revertNoRawat($no_rawat))->oneArray()]);
      exit();
    }

    public function postResumeSave()
    {
      $_POST['kd_dokter']	= $this->core->getUserInfo('username', $_SESSION['mlite_user']);

      if($this->core->mysql('resume_pasien')->where('no_rawat', $_POST['no_rawat'])->oneArray()) {
        $this->core->mysql('resume_pasien')
          ->where('no_rawat', $_POST['no_rawat'])
          ->save([
          'kd_dokter'  => $_POST['kd_dokter'],
          'keluhan_utama' => $_POST['keluhan_utama'],
          'jalannya_penyakit' => '-',
          'pemeriksaan_penunjang' => '-',
          'hasil_laborat' => '-',
          'diagnosa_utama' => $_POST['diagnosa_utama'],
          'kd_diagnosa_utama' => '-',
          'diagnosa_sekunder' => '-',
          'kd_diagnosa_sekunder' => '-',
          'diagnosa_sekunder2' => '-',
          'kd_diagnosa_sekunder2' => '-',
          'diagnosa_sekunder3' => '-',
          'kd_diagnosa_sekunder3' => '-',
          'diagnosa_sekunder4' => '-',
          'kd_diagnosa_sekunder4' => '-',
          'prosedur_utama' => $_POST['prosedur_utama'],
          'kd_prosedur_utama' => '-',
          'prosedur_sekunder' => '-',
          'kd_prosedur_sekunder' => '-',
          'prosedur_sekunder2' => '-',
          'kd_prosedur_sekunder2' => '-',
          'prosedur_sekunder3' => '-',
          'kd_prosedur_sekunder3' => '-',
          'kondisi_pulang'  => $_POST['kondisi_pulang'],
          'obat_pulang' => '-'
        ]);
      } else {
        $this->core->mysql('resume_pasien')->save([
          'no_rawat' => $_POST['no_rawat'],
          'kd_dokter'  => $_POST['kd_dokter'],
          'keluhan_utama' => $_POST['keluhan_utama'],
          'jalannya_penyakit' => '-',
          'pemeriksaan_penunjang' => '-',
          'hasil_laborat' => '-',
          'diagnosa_utama' => $_POST['diagnosa_utama'],
          'kd_diagnosa_utama' => '-',
          'diagnosa_sekunder' => '-',
          'kd_diagnosa_sekunder' => '-',
          'diagnosa_sekunder2' => '-',
          'kd_diagnosa_sekunder2' => '-',
          'diagnosa_sekunder3' => '-',
          'kd_diagnosa_sekunder3' => '-',
          'diagnosa_sekunder4' => '-',
          'kd_diagnosa_sekunder4' => '-',
          'prosedur_utama' => $_POST['prosedur_utama'],
          'kd_prosedur_utama' => '-',
          'prosedur_sekunder' => '-',
          'kd_prosedur_sekunder' => '-',
          'prosedur_sekunder2' => '-',
          'kd_prosedur_sekunder2' => '-',
          'prosedur_sekunder3' => '-',
          'kd_prosedur_sekunder3' => '-',
          'kondisi_pulang'  => $_POST['kondisi_pulang'],
          'obat_pulang' => '-'
        ]);
      }
      exit();
    }

    public function getSettings()
    {
        $this->assign['title'] = 'Pengaturan Modul Dokter Ralan';
        $this->assign['dokter_ralan'] = htmlspecialchars_array($this->settings('dokter_ralan'));
        return $this->draw('settings.html', ['settings' => $this->assign]);
    }

    public function postSaveSettings()
    {
        foreach ($_POST['dokter_ralan'] as $key => $val) {
            $this->settings('dokter_ralan', $key, $val);
        }
        $this->notify('success', 'Pengaturan telah disimpan');
        redirect(url([ADMIN, 'dokter_ralan', 'settings']));
    }

    public function getJavascript()
    {
        header('Content-type: text/javascript');
        $cek_pegawai = $this->core->mysql('pegawai')->where('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']))->oneArray();
        $cek_role = '';
        if($cek_pegawai) {
          $cek_role = $this->core->getPegawaiInfo('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']));
        }
        echo $this->draw(MODULES.'/dokter_ralan/js/admin/dokter_ralan.js', ['cek_role' => $cek_role]);
        exit();
    }

    public function getCss()
    {
        header('Content-type: text/css');
        echo $this->draw(MODULES.'/dokter_ralan/css/admin/dokter_ralan.css');
        exit();
    }

    private function _addHeaderFiles()
    {
        $this->core->addCSS(url('assets/jscripts/lightbox/lightbox.min.css'));
        $this->core->addJS(url('assets/jscripts/lightbox/lightbox.min.js'));

        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
        $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
        $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
        $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
        $this->core->addCSS(url([ADMIN, 'dokter_ralan', 'css']));
        $this->core->addJS(url([ADMIN, 'dokter_ralan', 'javascript']), 'footer');
    }

    protected function data_icd($table)
    {
        return new DB_ICD($table);
    }

}
