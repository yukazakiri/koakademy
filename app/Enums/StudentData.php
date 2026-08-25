<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentData: string
{
    case FilipinoFirstNamesMale = 'filipino_first_names_male';
    case FilipinoFirstNamesFemale = 'filipino_first_names_female';
    case FilipinoMiddleNames = 'filipino_middle_names';
    case FilipinoLastNames = 'filipino_last_names';
    case PhilippineCities = 'philippine_cities';
    case PhilippineProvinces = 'philippine_provinces';
    case PhilippineRegions = 'philippine_regions';
    case FilipinoSuffixes = 'filipino_suffixes';

    public static function filipinoFirstNamesMale(): array
    {
        return [
            'Juan',
            'Carlos',
            'Miguel',
            'Antonio',
            'Jose',
            'Pedro',
            'Pablo',
            'Luis',
            'Manuel',
            'Francisco',
            'Mark',
            'John',
            'James',
            'Michael',
            'David',
            'Joshua',
            'Daniel',
            'Matthew',
            'Andrew',
            'Joseph',
            'Robert',
            'William',
            'Richard',
            'Christopher',
            'Anthony',
            'Mark',
            'Paul',
            'Steven',
            'Kevin',
            'Brian',
            'Ronald',
            'Timothy',
            'Jeffrey',
            'Ryan',
            'Jacob',
            'Nicholas',
            'Eric',
            'Jonathan',
            'Steven',
            'Brandon',
            'Justin',
            'Samuel',
            'Benjamin',
            'Patrick',
            'Aaron',
            'Charles',
            'Adrian',
            'Kim',
            'Vincent',
            'Gerald',
            'Roderick',
            'Gilbert',
            'Fernando',
            'Raymond',
            'Gregory',
            'Harold',
            'Albert',
            'Dennis',
            'Lawrence',
            'Eugene',
            'Bobby',
            'Johnny',
            'Jack',
            'Joe',
            'Oscar',
            'Larry',
            'Scott',
            'Philip',
            'Craig',
            'Alan',
            'Shawn',
            'Derek',
            'Mario',
            'Randy',
            'Howard',
            'Roy',
            'Kyle',
            'Ethan',
            'Jordan',
            'Blake',
            'Ian',
            'Joel',
            'Neil',
            'Tracy',
            'Clinton',
            'Ross',
            'Marlon',
            'Lloyd',
            'Marvin',
            'Neil',
        ];
    }

    public static function filipinoFirstNamesFemale(): array
    {
        return [
            'Maria',
            'Ana',
            'Juana',
            'Carmen',
            'Rosa',
            'Elizabeth',
            'Grace',
            'Anna',
            'Patricia',
            'Catherine',
            'Mary',
            'Joseph',
            'Joy',
            'Marie',
            'Catherine',
            'Jennifer',
            'Jessica',
            'Amanda',
            'Sarah',
            'Stephanie',
            'Nicole',
            'Helen',
            'Diana',
            'Angela',
            'Melissa',
            'Julie',
            'Michelle',
            'Laura',
            'Sarah',
            'Karen',
            'Lisa',
            'Nancy',
            'Betty',
            'Margaret',
            'Sandra',
            'Ashley',
            'Kimberly',
            'Emily',
            'Donna',
            'Michelle',
            'Carol',
            'Sharon',
            'Michelle',
            'Laura',
            'Sarah',
            'Kimberly',
            'Elizabeth',
            'Lisa',
            'Jennifer',
            'Amanda',
            'Rosario',
            'Luz',
            'Concepcion',
            'Milagros',
            'Purificacion',
            'Remedios',
            'Esperanza',
            'Consolacion',
            'Felicidad',
            'Victoria',
            'Camille',
            'Katherine',
            'Megan',
            'Lauren',
            'Brittany',
            'Christina',
            'Tiffany',
            'Monica',
            'Rebecca',
            'Vanessa',
            'Alyssa',
            'Bianca',
            'Cassandra',
            'Danica',
            'Eliza',
            'Francesca',
            'Gabrielle',
            'Hillary',
            'Isabelle',
            'Jasmine',
        ];
    }

    public static function filipinoMiddleNames(): array
    {
        return [
            'Santos',
            'Garcia',
            'Reyes',
            'Cruz',
            'Mendoza',
            'Rodriguez',
            'Fernandez',
            'Lopez',
            'Gonzalez',
            'Perez',
            'DelaCruz',
            'DelaRosa',
            'DelaTorre',
            'DelPilar',
            'DelosSantos',
            'DeGuzman',
            'Mabini',
            'Bonifacio',
            'Rizal',
            'Aguinaldo',
            'Martinez',
            'Morales',
            'Diaz',
            'Ramos',
            'Soriano',
            'Torres',
            'Flores',
            'Vargas',
            'Castro',
            'Villanueva',
            'Aquino',
            'Macapagal',
            'Marcos',
            'Arroyo',
            'Duterte',
            'Roxas',
            'Aquino',
            'Magsaysay',
            'Quezon',
            'Romualdez',
            'Jimenez',
            'Ocampo',
            'Ofilada',
            'Pangilinan',
            'Sycz',
            'Abejuela',
            'Abuy',
            'Acuna',
            'Adorable',
            'Agbayani',
        ];
    }

    public static function filipinoLastNames(): array
    {
        return [
            'Santos',
            'Garcia',
            'Reyes',
            'Cruz',
            'Mendoza',
            'Rodriguez',
            'Fernandez',
            'Lopez',
            'Gonzalez',
            'Perez',
            'DelaCruz',
            'DelaRosa',
            'DelaTorre',
            'Diaz',
            'Ramos',
            'Torres',
            'Flores',
            'Vargas',
            'Castro',
            'Villanueva',
            'Aquino',
            'Marcos',
            'Arroyo',
            'Duterte',
            'Roxas',
            'Martinez',
            'Morales',
            'Sanchez',
            'Castillo',
            'Jimenez',
            'Villegas',
            'Mabini',
            'Bonifacio',
            'Rizal',
            'Aguinaldo',
            'Macapagal',
            'Magsaysay',
            'Quezon',
            'Romualdez',
            'Tan',
            'Chua',
            'Sy',
            'Co',
            'Uy',
            'Lim',
            'Ng',
            'Go',
            'Dy',
            'Lao',
            'Ong',
            'Aguilar',
            'Alegria',
            'Alvarez',
            'Andres',
            'Angeles',
            'Antonio',
            'Aparicio',
            'Arce',
            'Arellano',
            'Armstrong',
            'Bautista',
            'Bello',
            'Bernal',
            'Blanco',
            'Briana',
            'Bueno',
            'Burgos',
            'Bustamante',
            'Caballero',
            'Cabrera',
            'Calderon',
            'Calleja',
            'Camilo',
            'Candido',
            'Cardenas',
            'Cardoso',
            'Cares',
            'Carmona',
            'Carreon',
            'Casas',
            'Catajoy',
            'Cayanan',
            'Cayetano',
            'Cebu',
            'Cheng',
            'Chichi',
            'Ching',
            'Claudio',
            'Coloma',
            'Concepcion',
            'Cortez',
            'Crespo',
            'Cuenca',
            'Cuevas',
            'Dabu',
            'Dacumos',
            'Dalisay',
            'Dandan',
            'Datu',
            'Dayrit',
        ];
    }

    public static function sampleCities(): array
    {
        return [
            'Sample City',
            'Example City',
            'Demo City',
            'Test City',
        ];
    }

    public static function sampleProvinces(): array
    {
        return [
            'Example Province',
            'Sample Province',
            'Demo Province',
            'Test Province',
        ];
    }

    public static function sampleRegions(): array
    {
        return [
            'Sample Region',
            'Example Region',
            'Demo Region',
            'Test Region',
        ];
    }

    public static function filipinoSuffixes(): array
    {
        return [
            'Jr.',
            'Sr.',
            'III',
            'IV',
            'V',
            null,
            null,
            null,
            null,
            null,
        ];
    }

    public static function randomFullName(): string
    {
        $gender = fake()->randomElement([Gender::Male, Gender::Female]);
        $firstName = $gender === Gender::Male
            ? fake()->randomElement(self::filipinoFirstNamesMale())
            : fake()->randomElement(self::filipinoFirstNamesFemale());
        $middleName = fake()->randomElement(self::filipinoMiddleNames());
        $lastName = fake()->randomElement(self::filipinoLastNames());
        $suffix = fake()->randomElement(self::filipinoSuffixes());

        $fullName = "{$firstName} {$middleName} {$lastName}";
        if ($suffix) {
            $fullName .= " {$suffix}";
        }

        return $fullName;
    }

    public static function randomAddress(): string
    {
        $streetNumber = fake()->numberBetween(1, 999);
        $streetName = fake()->randomElement([
            'Example Street',
            'Sample Avenue',
            'Demo Road',
            'Test Lane',
            'Placeholder Boulevard',
        ]);
        $city = fake()->randomElement(self::sampleCities());
        $province = fake()->randomElement(self::sampleProvinces());

        return "{$streetNumber} {$streetName}, {$city}, {$province}";
    }

    public static function randomLrn(): string
    {
        return (string) fake()->numerify('############');
    }

    public static function randomPhoneNumber(): string
    {
        $prefixes = ['0917', '0918', '0919', '0920', '0921', '0922', '0923', '0924', '0925', '0926', '0927', '0928', '0929', '0930', '0931', '0932', '0933', '0934', '0935', '0936', '0937', '0938', '0939', '0940', '0941', '0942', '0943', '0944', '0945', '0946', '0947', '0948', '0949', '0950', '0951', '0952', '0953', '0954', '0955', '0956', '0957', '0958', '0959', '0960', '0961', '0962', '0963', '0964', '0965', '0966', '0967', '0968', '0969', '0970', '0971', '0972', '0973', '0974', '0975', '0976', '0977', '0978', '0979', '0980', '0981', '0982', '0983', '0984', '0985', '0986', '0987', '0988', '0989', '0990', '0991', '0992', '0993', '0994', '0995', '0996', '0997', '0998', '0999'];

        return fake()->randomElement($prefixes).fake()->numerify('#######');
    }

    public static function randomBirthDate(int $minAge = 16, int $maxAge = 25): string
    {
        $year = now()->year - fake()->numberBetween($minAge, $maxAge);
        $month = fake()->numberBetween(1, 12);
        $day = fake()->numberBetween(1, 28);

        return "{$year}-".mb_str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.mb_str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    }

    public static function calculateAge(string $birthDate): int
    {
        return (int) now()->diff(\Carbon\Carbon::parse($birthDate))->y;
    }

    public static function emergencyContact(): string
    {
        $firstName = fake()->randomElement(array_merge(self::filipinoFirstNamesMale(), self::filipinoFirstNamesFemale()));
        $lastName = fake()->randomElement(self::filipinoLastNames());
        $relationship = fake()->randomElement(['Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt', 'Grandmother', 'Grandfather', 'Guardian']);
        $phone = self::randomPhoneNumber();

        return "{$relationship}: {$firstName} {$lastName} - {$phone}";
    }

    public static function randomContacts(): array
    {
        return [
            'mobile' => self::randomPhoneNumber(),
            'facebook' => mb_strtolower((string) fake()->randomElement(self::filipinoFirstNamesMale())).'.'.mb_strtolower((string) fake()->randomElement(self::filipinoLastNames())).fake()->numerify('###'),
            'messenger' => null,
            'viber' => self::randomPhoneNumber(),
            'whatsapp' => self::randomPhoneNumber(),
        ];
    }
}
