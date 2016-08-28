<?php

namespace Laravel\Spark\Interactions\Auth;

use Laravel\Spark\Spark;
use Laravel\Spark\Invitation;
use Illuminate\Support\Facades\DB;
use Laravel\Spark\Contracts\Interactions\Subscribe;
use Laravel\Spark\Contracts\Http\Requests\Auth\RegisterRequest;
use Laravel\Spark\Contracts\Interactions\Settings\Teams\CreateTeam;
use Laravel\Spark\Contracts\Interactions\Auth\Register as Contract;
use Laravel\Spark\Contracts\Interactions\Settings\Teams\AddTeamMember;
use Laravel\Spark\Contracts\Interactions\Auth\CreateUser as CreateUserContract;

class Register implements Contract
{
    /**
     * {@inheritdoc}
     */
    public function handle(RegisterRequest $request)
    {
        return DB::transaction(function () use ($request) {
            return $this->subscribe($request, $this->createUser($request));
        });
    }

    /**
     * Create the user for the new registration.
     *
     * @param  RegisterRequest  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    protected function createUser(RegisterRequest $request)
    {
        $user = Spark::interact(CreateUserContract::class, [$request]);

        if (Spark::usesTeams()) {
            Spark::interact(self::class.'@configureTeamForNewUser', [$request, $user]);
        }

        return $user;
    }

    /**
     * Attach the user to a team if an invitation exists, or create a new team.
     *
     * @param  RegisterRequest  $request
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return void
     */
    public function configureTeamForNewUser(RegisterRequest $request, $user)
    {
        if ($invitation = $request->invitation()) {
            Spark::interact(AddTeamMember::class, [$invitation->team, $user]);

            $invitation->delete();
        } else {
            Spark::interact(CreateTeam::class, [$user, ['name' => $request->team]]);
        }

        $user->currentTeam();
    }

    /**
     * Subscribe the given user to a subscription plan.
     *
     * @param  RegisterRequest  $request
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return \Illuminate\Contracts\Auth\Authenticatable
     */
    protected function subscribe($request, $user)
    {
        if (! $request->hasPaidPlan()) {
            return $user;
        }

        Spark::interact(Subscribe::class, [
            $user, $request->plan(), true, $request->all()
        ]);

        return $user;
    }
}
