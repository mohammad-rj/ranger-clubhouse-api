<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use App\Models\ManualReview;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Photo;
use App\Models\Position;
use App\Models\PositionCredit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Slot;

use App\Mail\SlotSignup;
use App\Mail\TrainingSessionFullMail;
use App\Mail\TrainingSignup;

class PersonScheduleControllerTest extends TestCase
{
    use WithFaker;
    use RefreshDatabase;

    /*
     * have each test have a fresh user that is logged in,
     * and a set of positions.
     */

    public function setUp() : void
    {
        parent::setUp();

        $this->setting('ManualReviewDisabledAllowSignups', false);

        $this->signInUser();

        Mail::fake();
        // scheduling ends up sending lots of emails..
        $year = $this->year = date('Y');

        // Setup default (real world) positions
        $this->trainingPosition = factory(Position::class)->create(
            [
                'id'    => Position::DIRT_TRAINING,
                'title' => 'Training',
                'type'  => 'Training',
            ]
        );

        $this->dirtPosition = factory(Position::class)->create(
            [
                'id'    => Position::DIRT,
                'title' => 'Dirt',
                'type'  => 'Frontline',
            ]
        );

        // Used for ART training & signups
        $this->greenDotTrainingPosition = factory(Position::class)->create(
            [
                'id'    => Position::GREEN_DOT_TRAINING,
                'title' => 'Green Dot - Training',
                'type'  => 'Training',
            ]
        );

        $this->greenDotDirtPosition = factory(Position::class)->create(
            [
                'id'                   => Position::DIRT_GREEN_DOT,
                'title'                => 'Green Dot - Dirt',
                'type'                 => 'Frontline',
                'training_position_id' => $this->greenDotTrainingPosition->id,
            ]
        );

        $this->trainingSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day                   = (25 + $i);
            $this->trainingSlots[] = factory(Slot::class)->create(
                [
                    'begins'      => date("$year-05-$day 09:45:00"),
                    'ends'        => date("$year-05-$day 17:45:00"),
                    'position_id' => Position::DIRT_TRAINING,
                    'description' => "Training #$i",
                    'signed_up'   => 0,
                    'max'         => 10,
                    'min'         => 0,
                ]
            );
        }

        $this->dirtSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day               = (25 + $i);
            $this->dirtSlots[] = factory(Slot::class)->create(
                [
                    'begins'      => date("$year-08-$day 09:45:00"),
                    'ends'        => date("$year-08-$day 17:45:00"),
                    'position_id' => Position::DIRT,
                    'description' => "Dirt #$i",
                    'signed_up'   => 0,
                    'max'         => 10,
                    'min'         => 0,
                ]
            );
        }

        $this->greenDotTrainingSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day = (25 + $i);
            $this->greenDotTrainingSlots[] = factory(Slot::class)->create(
                [
                    'begins'      => date("$year-06-$day 09:45:00"),
                    'ends'        => date("$year-06-$day 17:45:00"),
                    'position_id' => Position::GREEN_DOT_TRAINING,
                    'description' => "GD Training #$i",
                    'signed_up'   => 0,
                    'max'         => 10,
                    'min'         => 0,
                ]
            );
        }

        $this->greenDotSlots = [];
        for ($i = 0; $i < 3; $i++) {
            $day                   = (25 + $i);
            $this->greenDotSlots[] = factory(Slot::class)->create(
                [
                    'begins'      => date("$year-08-$day 09:45:00"),
                    'ends'        => date("$year-08-$day 17:45:00"),
                    'position_id' => Position::DIRT_GREEN_DOT,
                    'description' => "Green Dot #$i",
                    'signed_up'   => 0,
                    'max'         => 10,
                    'min'         => 0,
                ]
            );
        }

    }


    /*
     * Find only the signups for a year.
     */

    public function testFindOnlyShiftSignupsForAYear()
    {
        $personId = $this->user->id;

        $this->addPosition(Position::TRAINING);
        $this->addPosition(Position::DIRT);

        $slotId = $this->dirtSlots[0]->id;

        factory(PersonSlot::class)->create(
            [
                'person_id' => $personId,
                'slot_id'   => $slotId,
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/schedule", [ 'year' => $this->year ]);
        $response->assertStatus(200);

        $response->assertJsonStructure([ 'schedules' => [ [ 'id' ] ] ]);
        $this->assertCount(1, $response->json()['schedules']);
        $this->assertEquals($slotId, $response->json()['schedules'][0]['id']);

    }


    /*
     * Find the available shifts and signups for a year.
     */

    public function testFindAvailableShiftsAndSignupsForYear()
    {
        $personId = $this->user->id;

        $this->addPosition(Position::TRAINING);
        $this->addPosition(Position::DIRT);

        $slotId = $this->dirtSlots[0]->id;

        factory(PersonSlot::class)->create(
            [
                'person_id' => $personId,
                'slot_id'   => $slotId,
            ]
        );

        $response = $this->json('GET', "person/{$this->user->id}/schedule", [ 'year' => $this->year, 'shifts_available' => 1 ]);
        $response->assertStatus(200);

        // Should match 6 shifts - 3 trainings and 3 dirt shift
        $this->assertCount(6, $response->json()['schedules']);

    }


    /*
     * Fail to find any shifts for the year
     */

    public function testDoNotFindAnyShiftsForYear()
    {
        $response = $this->json('GET', "person/{$this->user->id}/schedule", [ 'year' => $this->year, 'shifts_available' => 1 ]);
        $response->assertStatus(200);
        $response->assertJson([ 'schedules' => []]);

    }


    /*
     * Successfully signup for a dirt shift
     */

    public function testSignupForDirtShift()
    {
        $this->addPosition(Position::DIRT);
        $shift = $this->dirtSlots[0];

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success' ]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id'   => $shift->id,
            ]
        );

        $to = $this->user->email;

        Mail::assertSent(
            SlotSignup::class,
            function ($mail) use ($to) {
                return $mail->hasTo($to);
            }
        );

    }


    /*
     * Successfully signup for a training shift and email sent
     */

    public function testSignupForTrainingSession()
    {
        $this->addPosition(Position::TRAINING);
        $shift = $this->trainingSlots[0];

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success' ]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id'   => $shift->id,
            ]
        );

        $to = $this->user->email;

        // Training should not be overcapacity
        Mail::assertNotSent(TrainingSessionFullMail::class);

        Mail::assertSent(
            TrainingSignup::class,
            function ($mail) use ($to) {
                return $mail->hasTo($to);
            }
        );

    }


    /*
     * Successfully signup for a training shift and email T.A. session is full
     */

    public function testAlertTrainingAcademyWhenTrainingSessionIsFull()
    {
        $this->addPosition(Position::TRAINING);
        $shift = $this->trainingSlots[0];
        $shift->update([ 'max' => 1]);

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success' ]);
        Mail::assertSent(TrainingSessionFullMail::class);

    }


    /*
     * Failure to signup for a shift with no position
     */

    public function testDoNotAllowShiftSignupWithNoPosition()
    {
        $shift = $this->greenDotSlots[0];

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'no-position']);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id'   => $shift->id,
            ]
        );

    }


    /*
     * Prevent sign up for a full shift
     */

    public function testPreventSignupForFullShift()
    {
        $this->addPosition(Position::TRAINING);
        $shift = $this->trainingSlots[0];
        $shift->update([ 'signed_up' => 1, 'max' => 1 ]);

        $response = $this->json(
            'POST',
            "person/{$this->user->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'full' ]);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id'   => $shift->id,
            ]
        );

    }


    /*
     * Force a full shift signup by admin user
     */

    public function testAllowSignupForFullShiftIfAdmin()
    {
        $person = factory(Person::class)->create();
        $this->addPosition(Position::TRAINING, $person);
        $this->addRole(Role::ADMIN);

        $shift = $this->trainingSlots[0];
        $shift->update([ 'signed_up' => 1, 'max' => 1 ]);

        $response = $this->json(
            'POST',
            "person/{$person->id}/schedule",
            [
                'slot_id' => $shift->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success', 'full_forced' => true ]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $person->id,
                'slot_id'   => $shift->id,
            ]
        );

    }


    /*
     * Prevent user from signing up for multiple training sessions
     */

    public function testPreventMultipleEnrollmentsForTrainingSessions()
    {
        $personId = $this->user->id;
        $this->addPosition(Position::TRAINING);

        $previousTraining = $this->trainingSlots[0];

        factory(PersonSlot::class)->create(
            [
                'person_id' => $personId,
                'slot_id'   => $previousTraining->id,
            ]
        );

        $attemptedTraining = $this->trainingSlots[1];

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [
                'slot_id' => $attemptedTraining->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'multiple-enrollment' ]);

        $this->assertDatabaseMissing(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id'   => $attemptedTraining->id,
            ]
        );

    }

    /*
     * Alow the user to sign up for multiple part training sessions
     */

    public function testAllowMultiplePartTrainingSessionsSignup()
    {
        $personId = $this->user->id;
        $this->addPosition(Position::TRAINING);


        $part1 = factory(Slot::class)->create([
            'description' => 'Elysian Fields - Part 1',
            'position_id' =>  Position::TRAINING,
            'begins'      => date("Y-08-30 12:00:00"),
            'ends'        => date("Y-08-30 18:00:00")
        ]);

        $part2 = factory(Slot::class)->create([
            'description' => 'Elysian Fields - Part 2',
            'position_id' =>  Position::TRAINING,
            'begins'      => date("Y-08-31 12:00:00"),
            'ends'        => date("Y-08-31 18:00:00")
        ]);

        factory(PersonSlot::class)->create(
            [
                'person_id' => $personId,
                'slot_id'   => $part1->id,
            ]
        );

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [ 'slot_id' => $part2->id ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success' ]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $this->user->id,
                'slot_id'   => $part2->id,
            ]
        );

    }

    /*
     * Allow person to be signed up to multiple trainings for admin
     */

    public function testAllowMultipleEnrollmentsForTrainingSessionsIfAdmin()
    {
        $this->addRole(Role::ADMIN);

        $person = factory(Person::class)->create();
        $this->addPosition(Position::TRAINING, $person);
        $personId = $person->id;

        $previousTraining = $this->trainingSlots[0];

        factory(PersonSlot::class)->create(
            [
                'person_id' => $personId,
                'slot_id'   => $previousTraining->id,
            ]
        );

        $attemptedTraining = $this->trainingSlots[1];

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [
                'slot_id' => $attemptedTraining->id,
            ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success', 'multiple_forced' => true ]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id'   => $attemptedTraining->id,
            ]
        );

    }


    /*
     * Allow a trainer to sign themselves multiple time to a training session.
     */

    public function testAllowMultipleEnrollmentsForTrainingSessionsForTrainers()
    {
        $this->addRole(Role::ADMIN);
        $this->addPosition([ Position::TRAINING, Position::TRAINER ]);
        $personId = $this->user->id;

        $previousTraining = $this->trainingSlots[0];

        factory(PersonSlot::class)->create(
            [
                'person_id' => $personId,
                'slot_id'   => $previousTraining->id,
            ]
        );

        $attemptedTraining = $this->trainingSlots[1];

        $response = $this->json(
            'POST',
            "person/{$personId}/schedule",
            [ 'slot_id' => $attemptedTraining->id ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success', 'trainer_forced' => true ]);

        $this->assertDatabaseHas(
            'person_slot',
            [
                'person_id' => $personId,
                'slot_id'   => $attemptedTraining->id,
            ]
        );

    }


    /*
     * Remove a signup
     */

    public function testDeleteSignupSuccess()
    {
        $shift      = $this->dirtSlots[0];
        $personId   = $this->user->id;
        $personSlot = [
            'person_id' => $personId,
            'slot_id'   => $shift->id,
        ];

        factory(PersonSlot::class)->create($personSlot);

        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('person_slot', $personSlot);

    }


    /*
     * Fail to delete a non-existent sign up.
     */

    public function testFailWhenDeletingNonExistentSignup()
    {
        $shift    = $this->dirtSlots[0];
        $personId = $this->user->id;

        $response = $this->json('DELETE', "person/{$personId}/schedule/{$shift->id}");
        $response->assertStatus(404);

    }

    private function mockPhotoStatus($result)
    {
        $mock = $this->mock('alias:\App\Models\Photo');
        $mock->shouldReceive('retrieveStatus')->andReturn($result);

        return $mock;
    }

    private function mockManualReviewPass($result)
    {
        $mock = $this->mock('alias:\App\Models\ManualReview');
        $mock->shouldReceive('personPassedForYear')->andReturn($result);

        return $mock;
    }

    /*
     * Allow an active, who passed manual review, and has a photo to sign up.
     */

     public function testAllowActiveWhoPassedManualReviewAndHasPhoto()
     {
         $photoMock = $this->mockPhotoStatus('approved');
         $mrMock = $this->mockManualReviewPass(true);

         $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
             'year' => $this->year
         ]);

         $response->assertStatus(200);
         $response->assertJson([
             'permission'   => [
                 'signup_allowed'       => true,
                 'callsign_approved'    => true,
                 'manual_review_passed' => true,
             ]
         ]);
     }

     /*
      * Deny an active, who passed manual review, and has no photo.
      */

      public function testDenyActiveWithMissingPhoto()
      {
          $photoMock = $this->mockPhotoStatus('missing');
          $mrMock = $this->mockManualReviewPass(true);

          $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
              'year' => $this->year
          ]);

          $response->assertStatus(200);
          $response->assertJson([
              'permission'   => [
                  'signup_allowed'       => false,
                  'callsign_approved'    => true,
                  'manual_review_passed' => true,
                  'photo_status'         => 'missing',
              ]
          ]);
      }

      /*
       * Deny an active, who has photo, and did not pass manual review
       */

       public function testDenyActiveWhoDidNotPassManualReview()
       {
           $photoMock = $this->mockPhotoStatus('approved');
           $mrMock = $this->mockManualReviewPass(false);

           $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
               'year' => $this->year
           ]);

           $response->assertStatus(200);
           $response->assertJson([
               'permission'   => [
                   'signup_allowed'       => false,
                   'callsign_approved'    => true,
                   'manual_review_passed' => false,
                   'photo_status'         => 'approved',
               ]
           ]);
       }

     /*
      * Allow an auditor, who passed manual review, and has no photo to sign up.
      */

      public function testAllowAuditorWithNoPhotoAndPassedManualReview()
      {
          $this->user->update([ 'status' => 'auditor' ]);

          $photoMock = $this->mockPhotoStatus('missing');
          $mrMock = $this->mockManualReviewPass(true);

          $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
              'year' => $this->year
          ]);

          $response->assertStatus(200);
          $response->assertJson([
              'permission'   => [
                  'signup_allowed'       => true,
                  'callsign_approved'    => true,
                  'manual_review_passed' => true,
              ]
          ]);
      }

      /*
       * Deny an prospective who missed the manual review window
       */

       public function testDenyProspectiveWhoMissedManualReviewWindow()
       {
           $this->user->update([ 'status' => 'prospective' ]);

           $photoMock = $this->mockPhotoStatus('approved');

           $mrMock = $this->mockManualReviewPass(true);
           $mrMock->shouldReceive('prospectiveOrAlphaRankForYear')->andReturn(99);
           $mrMock->shouldReceive('countPassedProspectivesAndAlphasForYear')->andReturn(100);

           $this->setting('ManualReviewProspectiveAlphaLimit', 50);

           $response = $this->json('GET', "person/{$this->user->id}/schedule/permission", [
               'year' => $this->year
           ]);

           $response->assertStatus(200);
           $response->assertJson([
               'permission'   => [
                   'signup_allowed'       => false,
                   'callsign_approved'    => true,
                   'manual_review_passed' => true,
                   'manual_review_window_missed' => true,
               ]
           ]);
       }


}
