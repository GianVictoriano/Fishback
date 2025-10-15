<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CoverageRequest;
use Carbon\Carbon;

class CoverageRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requests = [
            [
                'event_name' => 'Annual University Foundation Day',
                'event_date' => Carbon::now()->addDays(15)->setTime(9, 0),
                'event_location' => 'University Gymnasium',
                'requester_name' => 'Maria Santos',
                'requester_email' => 'maria.santos@g.batstate-u.edu.ph',
                'description' => 'We need coverage for the opening ceremony and main program of our foundation day celebration. Expected attendance is around 2000 students and faculty.',
                'status' => 'pending',
            ],
            [
                'event_name' => 'Engineering Week Robotics Competition',
                'event_date' => Carbon::now()->addDays(7)->setTime(13, 0),
                'event_location' => 'Engineering Building, Room 301',
                'requester_name' => 'John Dela Cruz',
                'requester_email' => 'john.delacruz@g.batstate-u.edu.ph',
                'description' => 'Annual robotics competition featuring teams from different engineering departments. We would like photos and videos of the competition and awarding ceremony.',
                'status' => 'pending',
            ],
            [
                'event_name' => 'Literary Night: Poetry Reading',
                'event_date' => Carbon::now()->addDays(20)->setTime(18, 0),
                'event_location' => 'Arts and Sciences Building Auditorium',
                'requester_name' => 'Ana Reyes',
                'requester_email' => 'ana.reyes@g.batstate-u.edu.ph',
                'description' => 'Evening poetry reading event featuring student poets and guest speakers. We need coverage for our social media and yearbook.',
                'status' => 'approved',
            ],
            [
                'event_name' => 'Sports Fest Opening',
                'event_date' => Carbon::now()->subDays(5)->setTime(8, 0),
                'event_location' => 'University Sports Complex',
                'requester_name' => 'Carlos Mendoza',
                'requester_email' => 'carlos.mendoza@g.batstate-u.edu.ph',
                'description' => 'Opening ceremony of the annual sports fest with parade of athletes and torch lighting.',
                'status' => 'rejected',
            ],
        ];

        foreach ($requests as $request) {
            CoverageRequest::create($request);
        }
    }
}
