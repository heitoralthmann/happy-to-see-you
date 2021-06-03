<?php

namespace Drupal\HappyToSeeYou\Service;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * This class is responsible for holding all {renamed} logic.
 */
class GoogleSheetsRenamed {

  /**
   * The Google Drive service.
   *
   * @var \Google_Service_Drive
   */
  protected $googleDriveService;

  /**
   * The Google Sheets service.
   *
   * @var \Google_Service_Sheets
   */
  protected $googleSheetsService;

  /**
   * The @HappyToSeeYou.google_drive_folders_setup service.
   *
   * @var \Drupal\HappyToSeeYou\Service\GoogleDriveFoldersSetup
   */
  protected $foldersSetup;

  /**
   * Class constructor.
   */
  public function __construct(GoogleDriveApi $google_drive_api, GoogleDriveFoldersSetup $google_drive_folders_setup) {
    $this->googleDriveService  = new \Google_Service_Drive($google_drive_api->getClient());
    $this->googleSheetsService = new \Google_Service_Sheets($google_drive_api->getClient());
    $this->foldersSetup        = $google_drive_folders_setup;
  }

  /**
   * Creates a new {renamed} file from a given template and company.
   *
   * What it does is that it copies the {renamed}
   * template for the given year, and paste it under the
   * `{renamed}/XYZ/<foo>` folder on Google Drive.
   *
   * Each company in the system is supposed to have a unique
   * copy of the {renamed} template for that year, that is
   * going to be used as its {renamed} file.
   *
   * @param \Drupal\node\NodeInterface $foo_template
   *   The template for the new {renamed} file we're going to create.
   * @param \Drupal\node\NodeInterface $bar
   *   The company that is going to own the new {renamed} file.
   *
   * @return \Google_Service_Drive_DriveFile
   *   The new file that's been just created.
   */
  public function createCompanyRenamed(NodeInterface $foo_template, NodeInterface $bar): \Google_Service_Drive_DriveFile {
    $original_template_file_id = $this->extractGoogleDriveFileIdFromRenamedRelatedNode($foo_template);
    $foo_year             = $this->getYearFromRenamedTemplate($foo_template);
    $foo_year_folder_id   = $this->foldersSetup->getGoogleSheetsRenamedubmissionYearFolderId($foo_year);

    $new_foo = new \Google_Service_Drive_DriveFile();
    $new_foo->setName($bar->label());
    $new_foo->setParents([$foo_year_folder_id]);

    $permissions = $this->getRenamedFilePermissions();

    $copiedFile = $this->googleDriveService->files->copy($original_template_file_id, $new_foo, ['fields' => 'id, name, webViewLink']);
    $this->googleDriveService->permissions->create($copiedFile->getId(), $permissions);

    return $copiedFile;
  }

  /**
   * Gets the year from a given {renamed} template.
   *
   * @param \Drupal\node\NodeInterface $foo_template
   *   The {renamed} template to get the year from.
   *
   * @return int
   *   The {renamed} year.
   */
  private function getYearFromRenamedTemplate(NodeInterface $foo_template): int {
    return (int) $foo_template->field_foo->value;
  }

  /**
   * Extracts G Drive file id from a {renamed} a/b/c node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The {renamed} template node that contains the URL to the G Drive file.
   *
   * @return string
   *   The Google Drive file ID or NULL if none is found.
   */
  public function extractGoogleDriveFileIdFromRenamedRelatedNode(NodeInterface $node): ?string {
    $file_url = is_object($node->field_google_sheet_url) ? $node->field_google_sheet_url->uri : '';
    preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $file_url, $matches);

    if (!empty($matches)) {
      return $matches[1];
    }

    return NULL;
  }

  /**
   * Gets the proper Google Drive file permissions for {renamed} files.
   *
   * @return \Google_Service_Drive_Permission
   *   Google Drive permissions for the {renamed} file.
   */
  private function getRenamedFilePermissions(): \Google_Service_Drive_Permission {
    $permissions = new \Google_Service_Drive_Permission();
    $permissions->setRole('writer');
    $permissions->setType('anyone');

    return $permissions;
  }

  /**
   * Creates a {renamed} submission node.
   *
   * @param \Drupal\node\NodeInterface $bar
   *   The company we're creating the {renamed} submission for.
   * @param int $year
   *   The year for the {renamed} submission.
   * @param string $google_sheet_url
   *   The URL for accessing the {renamed} submission Google Sheet.
   * @param int $status
   *   Whether the node should be published or not.
   */
  public function createGoogleSheetsRenamedubmissionNode(NodeInterface $bar, int $year, string $google_sheet_url, int $status = 1) {
    $foo_submission = Node::create([
      'type'        => 'foo_submission',
      'status'      => $status,
      'title'       => "[{$year}] {$bar->label()}",
      'field_foo'  => $year,
      'field_baz' => [
        'target_id' => $bar->id(),
      ],
      'field_google_sheet_url' => [
        'uri' => $google_sheet_url,
      ],
    ]);

    $foo_submission->save();
  }

  /**
   * Gets a {renamed} submission status.
   *
   * @param \Drupal\node\NodeInterface $foo_submission
   *   The {renamed} submission node to get the submission status for.
   *
   * @return string
   *   The {renamed} submission status.
   */
  public function getSubmissionStatus(NodeInterface $foo_submission): string {
    $file_id = $this->extractGoogleDriveFileIdFromRenamedRelatedNode($foo_submission);

    $response = $this->googleSheetsService->spreadsheets_values->get($file_id, 'B71');
    $values = $response->getValues();

    return strtolower($values[0][0]);
  }

  /**
   * Writes the company name to the {renamed} submission file.
   *
   * @param \Drupal\node\Entity\NodeInterface $foo_submission
   *   The {renamed} submission node.
   * @param string $bar_name
   *   The company name.
   */
  public function writeCompanyName(NodeInterface $foo_submission, string $bar_name) {
    $file_id = $this->extractGoogleDriveFileIdFromRenamedRelatedNode($foo_submission);
    $range   = 'B212';
    $body    = new \Google_Service_Sheets_ValueRange([
      'values' => [
        [$bar_name],
      ],
    ]);
    $params  = [
      'valueInputOption' => 'RAW',
    ];

    $this->googleSheetsService->spreadsheets_values->update($file_id, $range, $body, $params);
  }

  /**
   * Tells whether the {renamed} file url is a Google Drive file.
   *
   * @param \Drupal\node\Entity\NodeInterface $foo_submission
   *   The {renamed} submission node we'll make the check on.
   *
   * @return bool
   *   Whether it's a Google Drive file or not.
   */
  public function isGoogleDriveFile(NodeInterface $foo_submission): bool {
    return (bool) $this->extractGoogleDriveFileIdFromRenamedRelatedNode($foo_submission);
  }

}
