<?php

namespace App\Observers;

use App\UserAppInfo;

class UserAppInfoObserver
{
  /**
   * Handle the user app info "created" event.
   *
   * @param  \App\UserAppInfo $userAppInfo
   * @return void
   */
  public function creating(UserAppInfo $userAppInfo)
  {
    UserAppInfo::where('push_key', $userAppInfo->push_key)->update(['push_alarm' => 'N']);
  }

  public function created(UserAppInfo $userAppInfo)
  {
    //
  }

  /**
   * Handle the user app info "updated" event.
   *
   * @param  \App\UserAppInfo $userAppInfo
   * @return void
   */
  public function updating(UserAppInfo $userAppInfo)
  {
    UserAppInfo::where('push_key', $userAppInfo->push_key)->update(['push_alarm' => 'N']);
  }

  public function updated(UserAppInfo $userAppInfo)
  {

  }

  /**
   * Handle the user app info "deleted" event.
   *
   * @param  \App\UserAppInfo $userAppInfo
   * @return void
   */
  public function deleted(UserAppInfo $userAppInfo)
  {
    //
  }

  /**
   * Handle the user app info "restored" event.
   *
   * @param  \App\UserAppInfo $userAppInfo
   * @return void
   */
  public function restored(UserAppInfo $userAppInfo)
  {
    //
  }

  /**
   * Handle the user app info "force deleted" event.
   *
   * @param  \App\UserAppInfo $userAppInfo
   * @return void
   */
  public function forceDeleted(UserAppInfo $userAppInfo)
  {
    //
  }
}
