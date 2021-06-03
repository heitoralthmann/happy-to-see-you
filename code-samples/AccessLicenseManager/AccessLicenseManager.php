<?php

declare(strict_types = 1);

namespace Drupal\HappyToSeeYou\Business\AccessLicenseManager;

use Drupal\HappyToSeeYou\Util\UserDataInterface;
use Drupal\HappyToSeeYou\Util\UserRolesInterface;
use Drupal\user\UserInterface;

/**
 * Class AccessLicenseManager.
 */
class AccessLicenseManager implements AccessLicenseManagerInterface {

  /**
   * An object representing the current user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $currentUser;

  /**
   * Drupal\HappyToSeeYou\Util\UserDataInterface definition.
   *
   * @var \Drupal\HappyToSeeYou\Util\UserDataInterface
   */
  protected $userData;

  /**
   * Class constructor.
   *
   * @param \Drupal\user\UserInterface $current_user
   *   An object representing the current user.
   * @param \Drupal\HappyToSeeYou\Util\UserDataInterface $user_data
   *   The @HappyToSeeYou.user_data service instance.
   */
  public function __construct(UserInterface $current_user, UserDataInterface $user_data) {
    $this->currentUser = $current_user;
    $this->userData    = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(): bool {
    return $this->isActive()
      || (int) $this->currentUser->id() === 1
      || $this->currentUser->hasRole(UserRolesInterface::ADMINISTRADOR_ROLE);
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(int $uid = NULL): bool {
    $expiration_date = $this->userData->get(UserDataInterface::EXPIRATION_DATE_VARIABLE_KEY, $uid);

    return $expiration_date && (time() <= (int) $expiration_date);
  }

  /**
   * {@inheritdoc}
   */
  public function setExpirationDate(string $purchased_time_period, int $uid = NULL): void {
    $now = time();
    $current_access_license_expiration_date = $this->getExpirationDate($uid);

    $base_datetime = (!is_null($current_access_license_expiration_date) && $current_access_license_expiration_date > $now)
      ? $current_access_license_expiration_date
      : $now;

    $expiration_date = strtotime('today + ' . $purchased_time_period, $base_datetime);

    $this->userData->set(UserDataInterface::EXPIRATION_DATE_VARIABLE_KEY, $expiration_date, $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpirationDate(int $uid = NULL): ?int {
    return (int) $this->userData->get(UserDataInterface::EXPIRATION_DATE_VARIABLE_KEY, $uid);
  }

}
