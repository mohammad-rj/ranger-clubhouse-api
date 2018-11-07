<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\ApiController;

use App\Models\Person;
use App\Models\Alert;
use App\Models\AlertPerson;

class AlertPersonController extends ApiController
{
    /*
     * Return an array of AlertPerson for a person
     */
    public function index(Person $person)
    {
        $this->authorize('view', [ AlertPerson::class, $person]);

        return response()->json([ 'alerts' => AlertPerson::findAllForPerson($person->id)]);
    }

    /*
     * Update all the alerts for a person in bulk.
     */

    public function update(Person $person)
    {
        $this->authorize('update', [ AlertPerson::class, $person ]);

        $data = request()->validate([
            'alerts.*.id'  => 'required|integer',
            'alerts.*.use_email' => 'required|boolean',
            'alerts.*.use_sms'   => 'required|boolean'
        ]);

        foreach ($data['alerts'] as $pref) {
            $alert = Alert::find($pref['id']);
            if (!$alert) {
                continue;
            }

            $alertPerson = AlertPerson::findOrCreateForPerson($person->id, $alert->id);
            $alertPerson->use_email = $pref['use_email'];
            $alertPerson->use_sms = $pref['use_sms'];
            $alertPerson->save();
        }

        return $this->success();
    }
}
