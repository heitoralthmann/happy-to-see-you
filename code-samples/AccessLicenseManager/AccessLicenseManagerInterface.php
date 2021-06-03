<?php

declare(strict_types = 1);

namespace Drupal\HappyToSeeYou\Business\AccessLicenseManager;

/**
 * Provides methods for managing access licenses.
 */
interface AccessLicenseManagerInterface {

  /**
   * Checks user access based on access licenses.
   *
   * Admin users must bypass access control.
   *
   * @return bool
   *   Whether access is granted to the current user.
   */
  public function checkAccess(): bool;

  /**
   * Checks whether the given user has an active access license.
   *
   * This method must basically check if the expiration date
   * wasn't reached yet.
   *
   * @param int $uid
   *   The id of the user to check access for.
   *   If no user id is provided, check access for the current
   *   logged in user.
   *
   * @return bool
   *   Whether the access license is active.
   */
  public function isActive(int $uid = NULL): bool;

  /**
   * Sets the date and time when the access license will be due.
   *
   * If the user has an active access license, the new expiration date
   * will be added on top of that (will be appended to it).
   *
   * @param string $purchased_time_period
   *   The purchased amount of time as a relative date/time string.
   *   Ex: '3 months'.
   * @param int $uid
   *   The ID of the user to set the access license's expiration date for.
   */
  public function setExpirationDate(string $purchased_time_period, int $uid = NULL): void;

  /**
   * Returns the timestamp for the access license expiration date.
   *
   * @param int $uid
   *   The ID of the user to get the expiration date for.
   *
   * @return int|null
   *   A unix timestamp representing the time where the access
   *   lincese will be due or NULL if the user does not have
   *   this information.
   */
  public function getExpirationDate(int $uid = NULL): ?int;

}
