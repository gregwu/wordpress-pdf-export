<?php

namespace WPForms\Pro\Admin\Entries\Export;

use WP_Filesystem_Base;
use WPForms\Helpers\Transient;



if (!defined('RELATIVE_PATH')) define('RELATIVE_PATH','');

//include_once(RELATIVE_PATH.'html2fpdf.php');
include_once(RELATIVE_PATH.'fpdf.php');
//include_once(RELATIVE_PATH.'pdf.php');
include_once(RELATIVE_PATH.'HTML2FPDF.php');






/**
 * File-related routines.
 *
 * @since 1.5.5
 */
class File {

	/**
	 * Instance of Export Class.
	 *
	 * @since 1.5.5
	 *
	 * @var \WPForms\Pro\Admin\Entries\Export\Export
	 */
	protected $export;

	/**
	 * Constructor.
	 *
	 * @since 1.5.5
	 *
	 * @param \WPForms\Pro\Admin\Entries\Export\Export $export Instance of Export.
	 */
	public function __construct( $export ) {

		$this->export = $export;

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.5.5
	 */
	public function hooks() {

		add_action( 'wpforms_tools_init', [ $this, 'entries_export_download_file' ] );
		add_action( 'wpforms_tools_init', [ $this, 'single_entry_export_download_file' ] );
		add_action( Export::TASK_CLEANUP, [ $this, 'remove_old_export_files' ] );
	}

	/**
	 * Export helper. Write data to a temporary .csv file.
	 *
	 * @since 1.5.5
	 *
	 * @param array $request_data Request data array.
	 */
	public function write_csv( $request_data ) {

		$export_file = $this->get_tmpfname( $request_data );

		if ( empty( $export_file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$f         = fopen( 'php://temp', 'wb+' );
		$enclosure = '"';

		fputcsv( $f, $request_data['columns_row'], $this->export->configuration['csv_export_separator'], $enclosure );

		for ( $i = 1; $i <= $request_data['total_steps']; $i ++ ) {

			$entries = wpforms()->get( 'entry' )->get_entries( $request_data['db_args'] );

			foreach ( $this->export->ajax->get_entry_data( $entries ) as $entry ) {
				fputcsv( $f, $entry, $this->export->configuration['csv_export_separator'], $enclosure );
			}

			$request_data['db_args']['offset'] = $i * $this->export->configuration['entries_per_step'];
		}

		rewind( $f );

		$file_contents = stream_get_contents( $f );

		$this->put_contents( $export_file, $file_contents );
	}

	/**
	 * Write data to .xlsx file.
	 *
	 * @since 1.6.5
	 *
	 * @param array $request_data Request data array.
	 */
	public function write_xlsx( $request_data ) {

		$export_file = $this->get_tmpfname( $request_data );

		if ( empty( $export_file ) ) {
			return;
		}

		$writer = new \XLSXWriter();

		$writer->setTempDir( $this->get_tmpdir() );

		$sheet_name = 'WPForms';

		$widths = [];

		foreach ( $request_data['columns_row'] as $header ) {
			$widths[] = strlen( $header ) + 2;
		}

		$writer->writeSheetHeader( $sheet_name, array_fill_keys( $request_data['columns_row'], 'string' ), [ 'widths' => $widths ] );

		for ( $i = 1; $i <= $request_data['total_steps']; $i ++ ) {

			$entries = wpforms()->get( 'entry' )->get_entries( $request_data['db_args'] );

			foreach ( $this->export->ajax->get_entry_data( $entries ) as $entry ) {

				$writer->writeSheetRow( $sheet_name, $entry, [ 'wrap_text' => true ] );
			}

			$request_data['db_args']['offset'] = $i * $this->export->configuration['entries_per_step'];
		}

		$file_contents = $writer->writeToString();

		$this->put_contents( $export_file, $file_contents );
	}


	public function write_pdf( $request_data ) {

		$export_file = $this->get_tmpfname( $request_data );

		if ( empty( $export_file ) ) {
			return;
		}


		$title = $request_data['form_data']['settings']['form_title'];
		$articleurl = $request_data['form_data']['settings']['form_url'];
		$pdf = new HTML2FPDF('P','mm','A4',$articleurl,false);
		$pdf->AddPage();
		$pdf->SetFont('Arial','B',16);
		$pdf->Cell(40,10, $request_data['form_data']['settings']['form_title'], 0, 1);
		$pdf->SetFont('Arial','B',12);
		//$pdf->Line(10, 20, 200, 20);
		$pdf->Ln();

//error_log(print_r($request_data,true));
//var_dump(json_encode($request_data));

		for ( $i = 1; $i <= $request_data['total_steps']; $i ++ ) {

			$entries = wpforms()->get( 'entry' )->get_entries( $request_data['db_args'] );
   error_log($entries[0]->fields, 3, '/tmp/mylog.log');
   error_log("\n\n", 3, '/tmp/mylog.log');
			$data = json_decode($entries[0]->fields);
			
			foreach(  $data  as $field ) {
				$value = trim($field->value);
				
				if( $field->type == 'divider'  ) {
					$pdf->SetFont('Courier','B',12);
					$pdf->SetTextColor(10,154,220);//0a 9a dc
					$pdf->Ln();
					if($field->name == "Ethical Considerations"){
						//break;
					}
					$pdf->Cell(40,6, trim($field->name) , 0, 0);
				}
				else {
					if(empty($value)) continue;
					$pdf->SetFont('Courier','BI',10);
					$pdf->SetTextColor(0,0,0);
					if(trim($field->name) == 'General Information' ){
						$pdf->SetTextColor(0,0,0);
						$pdf->SetFont('Courier','',8);
						$pdf->setFillColor(240);
						$pdf->MultiCell(92,5,  $field->value, 0, 1, true);
						continue;
					}
					else if(in_array($field->name, ['Model Type','Organization','Data Input','Model Date','Model Version','License'])) {
						$pdf->Cell(40,6, trim($field->name) , 0, 0);
					}
					else {
						$pdf->Cell(40,6, trim($field->name) , 0, 2);
					}
					
				}


				

				$pdf->SetTextColor(0,0,0);
				$pdf->SetFont('Courier','',8);
				//$pdf->MultiCell(92,5,  $field->value, 0, 1);
				
				$pdf->WriteHTML(92, 5, $value );
				$pdf->Ln();
				
			
			}	
				
		}
		$pdf->Output();
		$this->put_contents( $export_file, $pdf );

	}
	/**
	 * Tmp files directory.
	 *
	 * @since 1.5.5
	 *
	 * @return string Temporary files directory path.
	 */
	public function get_tmpdir() {

		$upload_dir  = wpforms_upload_dir();
		$upload_path = $upload_dir['path'];

		$export_path = trailingslashit( $upload_path ) . 'export';
		if ( ! file_exists( $export_path ) ) {
			wp_mkdir_p( $export_path );
		}

		// Check if the .htaccess exists in the upload directory, if not - create it.
		wpforms_create_upload_dir_htaccess_file();

		// Check if the index.html exists in the directories, if not - create it.
		wpforms_create_index_html_file( $upload_path );
		wpforms_create_index_html_file( $export_path );

		// Normalize slashes for Windows.
		$export_path = wp_normalize_path( $export_path );

		return $export_path;
	}

	/**
	 * Full pathname of the tmp file.
	 *
	 * @since 1.5.5
	 *
	 * @param array $request_data Request data.
	 *
	 * @return string Temporary file full pathname.
	 */
	public function get_tmpfname( $request_data ) {

		if ( empty( $request_data ) ) {
			return '';
		}

		$export_dir  = $this->get_tmpdir();
		$export_file = $export_dir . '/' . sanitize_key( $request_data['request_id'] ) . '.tmp';

		touch( $export_file );

		return $export_file;
	}

	/**
	 * Send HTTP headers for .csv file download.
	 *
	 * @since 1.5.5.1
	 *
	 * @param string $file_name File name.
	 */
	public function http_headers( $file_name ) {

		$file_name = empty( $file_name ) ? 'wpforms-entries.csv' : $file_name;

		nocache_headers();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );
		header( 'Content-Transfer-Encoding: binary' );
	}

	/**
	 * Output the file.
	 * The WPFORMS_SAVE_ENTRIES_PATH is introduced for facilitating e2e tests.
	 * When the constant is used, download isn't prompted and the file gets downloaded in the provided path.
	 *
	 *
	 * @since 1.6.0.2
	 *
	 * @param array $request_data Request data.
	 *
	 * @throws \Exception In case of file error.
	 */
	public function output_file( $request_data ) {

		$export_file = $this->get_tmpfname( $request_data );

		if ( empty( $export_file ) ) {
			throw new \Exception( $this->export->errors['unknown_request'] );
		}

		clearstatcache( true, $export_file );

		// Remove the transient.
		Transient::delete( 'wpforms-tools-entries-export-request-' . $request_data['request_id'] );

		if ( ! is_readable( $export_file ) || is_dir( $export_file ) ) {
			throw new \Exception( $this->export->errors['file_not_readable'] );
		}

		if ( @filesize( $export_file ) === 0 ) { //phpcs:ignore
			throw new \Exception( $this->export->errors['file_empty'] );
		}

		$entry_suffix = ! empty( $request_data['db_args']['entry_id'] ) ? '-entry-' . $request_data['db_args']['entry_id'] : '';

		$file_name = 'wpforms-' . $request_data['db_args']['form_id'] . '-' . sanitize_file_name( get_the_title( $request_data['db_args']['form_id'] ) ) . $entry_suffix . '-' . current_time( 'Y-m-d-H-i-s' ) . '.' . $request_data['type'];

		if ( defined( 'WPFORMS_SAVE_ENTRIES_PATH' ) ) {
			$this->put_contents( WPFORMS_SAVE_ENTRIES_PATH . $file_name, file_get_contents( $export_file ) );
		} else {
			$this->http_headers( $file_name );
			readfile( $export_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		}

		// Schedule clean up.
		$this->schedule_remove_old_export_files();

		exit;
	}

	/**
	 * Entries export file download.
	 *
	 * @since 1.5.5
	 *
	 * @throws \Exception Try-catch.
	 */
	public function entries_export_download_file() {

		$args = $this->export->data['get_args'];

		if ( $args['action'] !== 'wpforms_tools_entries_export_download' ) {
			return;
		}

		try {

			// Security check.
			if (
				! wp_verify_nonce( $args['nonce'], 'wpforms-tools-entries-export-nonce' ) ||
				! wpforms_current_user_can( 'view_entries' )
			) {
				throw new \Exception( $this->export->errors['security'] );
			}

			// Check for request_id.
			if ( empty( $args['request_id'] ) ) {
				throw new \Exception( $this->export->errors['unknown_request'] );
			}

			// Get stored request data.
			$request_data = Transient::get( 'wpforms-tools-entries-export-request-' . $args['request_id'] );

			$this->output_file( $request_data );

		} catch ( \Exception $e ) {
			// phpcs:disable
			$error = $this->export->errors['common'] . '<br>' . $e->getMessage();
			if ( wpforms_debug() ) {
				$error .= '<br><b>WPFORMS DEBUG</b>: ' . $e->__toString();
			}
			$error = str_replace( "'", '&#039;', $error );
			$js_error = str_replace( "\n", '', $error );

			echo "
			<script>
				( function() {
					var w = window;
					if (
						w.frameElement !== null &&
						w.frameElement.nodeName === 'IFRAME' &&
						w.parent.jQuery
					) {
						w.parent.jQuery( w.parent.document ).trigger( 'csv_file_error', [ '" . wp_slash( $js_error ) . "' ] );
					}
				} )();
			</script>
			<pre>" . $error . '</pre>';
			exit;
			// phpcs:enable
		}
	}

	/**
	 * Single entry export file download.
	 *
	 * @since 1.5.5
	 *
	 * @throws \Exception Try-catch.
	 */
	public function single_entry_export_download_file() {

		$args = $this->export->data['get_args'];

		if ( 'wpforms_tools_single_entry_export_download' !== $args['action'] ) {
			return;
		}

		try {

			// Check for form_id.
			if ( empty( $args['form_id'] ) ) {
				throw new \Exception( $this->export->errors['unknown_form_id'] );
			}

			// Check for entry_id.
			if ( empty( $args['entry_id'] ) ) {
				throw new \Exception( $this->export->errors['unknown_entry_id'] );
			}

			// Security check.
			if (
				! wp_verify_nonce( $args['nonce'], 'wpforms-tools-single-entry-export-nonce' ) ||
				! wpforms_current_user_can( 'view_entries' )
			) {
				throw new \Exception( $this->export->errors['security'] );
			}

			// Get stored request data.
			$request_data = $this->export->ajax->get_request_data( $args );

			$this->export->ajax->request_data = $request_data;

			if ( empty( $request_data['type'] ) || $request_data['type'] === 'csv' ) {
				$this->write_csv( $request_data );
			} elseif ( $request_data['type'] === 'xlsx' ) {
				$this->write_xlsx( $request_data );
			
			} elseif ( $request_data['type'] === 'pdf' ) {
				$this->write_pdf( $request_data );
			}

			$this->output_file( $request_data );

		} catch ( \Exception $e ) {

			$error = $this->export->errors['common'] . '<br>' . $e->getMessage();
			if ( wpforms_debug() ) {
				$error .= '<br><b>WPFORMS DEBUG</b>: ' . $e->__toString();
			}

			\WPForms\Admin\Notice::error( $error );
		}
	}

	/**
	 * Register a cleanup task to remove temp export files.
	 *
	 * @since 1.6.5
	 */
	public function schedule_remove_old_export_files() {

		$tasks = wpforms()->get( 'tasks' );

		if ( $tasks->is_scheduled( Export::TASK_CLEANUP ) !== false ) {
			return;
		}

		$tasks->create( Export::TASK_CLEANUP )
		      ->recurring( time() + 60, $this->export->configuration['request_data_ttl'] )
		      ->params()
		      ->register();
	}

	/**
	 * Clear temporary files.
	 *
	 * @since 1.5.5
	 * @since 1.6.5 Stop using transients. Now we use ActionScheduler for this task.
	 */
	public function remove_old_export_files() {

		clearstatcache();

		$files = glob( $this->get_tmpdir() . '/*' );
		$now   = time();

		foreach ( $files as $file ) {
			if (
				is_file( $file ) &&
				pathinfo( $file, PATHINFO_BASENAME ) !== 'index.html' &&
				( $now - filemtime( $file ) ) > $this->export->configuration['request_data_ttl']
			) {
				unlink( $file );
			}
		}
	}

	/**
	 * Put file contents using WP Filesystem.
	 *
	 * @since 1.7.3
	 *
	 * @param string $export_file   Export filename.
	 * @param string $file_contents File contents.
	 *
	 * @return void
	 */
	private function put_contents( $export_file, $file_contents ) {

		global $wp_filesystem;

		if (
			! $wp_filesystem instanceof WP_Filesystem_Base ||
			! WP_Filesystem( request_filesystem_credentials( site_url() ) )
		) {
			return;
		}

		$wp_filesystem->put_contents(
			$export_file,
			$file_contents
		);
	}
}
