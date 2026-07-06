<?php



namespace App\Http\Middleware;



use Closure;

use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;



class CheckProfileAccess

{

    public function handle(

        Request $request,

        Closure $next,

        string $profile,

        ?string $scope = null

    ): Response {



        if (

            auth()->id() === 1

        ) {

            return $next($request);

        }



        $profile =

            $this->normalizeProfile($profile);



        if (

            $profile === 'admin' &&

            $scope === 'tenant-admin' &&

            $this->userHasTenantAdminProfile()

        ) {

            return $next($request);

        }





        if (

            ! userHasProfile($profile)

        ) {

            abort(403);

        }



        return $next($request);

    }



    private function normalizeProfile(

        ?string $profile

    ): ?string {



        $profile = trim(

            mb_strtolower(

                $profile ?? ''

            )

        );



        return match ($profile) {

            'driver',

            'motorista' =>

                'driver',



            'mechanic',

            'mecanico',

            'mecânico' =>

                'mechanic',



            'supervisor' =>

                'supervisor',



            'manager',

            'gestor',

            'gestor operacional',

            'gestor_operacional' =>

                'manager',



            'admin' =>

                'admin',



            default =>

                $profile ?: null,

        };

    }



    private function userHasTenantAdminProfile(): bool

    {

        $user = auth()->user();



        if (! $user) {

            return false;

        }



        return $user

            ->divisionAccesses()

            ->where('tenant_id', $user->tenant_id)

            ->where('profile', 'admin')

            ->where('active', true)

            ->exists();

    }

}
