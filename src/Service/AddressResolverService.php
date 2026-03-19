<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\City;
use App\Entity\Department;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Resolves (finds or creates) Address, City and Department from Google Places data.
 * Reusable by both the registration flow and the admin CRUD.
 */
class AddressResolverService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * @param string      $placeId          Google Place ID (required)
     * @param string      $formattedAddress  Human-readable address string (required)
     * @param string      $cityName          Locality name from Places (required)
     * @param string      $departmentName    Administrative area level 2 long_name
     * @param string      $departmentCode    Administrative area level 2 short_name
     * @param string|null $postalCode        Postal code (optional)
     * @param string|null $lat               Latitude (optional)
     * @param string|null $lng               Longitude (optional)
     *
     * @return Address|null  null if required data is missing
     */
    public function resolve(
        string $placeId,
        string $formattedAddress,
        string $cityName,
        string $departmentName,
        string $departmentCode,
        ?string $postalCode = null,
        ?string $lat = null,
        ?string $lng = null,
    ): ?Address {
        if ($placeId === '' || $cityName === '') {
            return null;
        }

        $department = $this->resolveDepartment($departmentName, $departmentCode);
        $city       = $this->resolveCity($cityName, $department);
        $address    = $this->resolveAddress($placeId, $formattedAddress, $lat, $lng, $postalCode, $city);

        return $address;
    }

    private function resolveDepartment(string $name, string $code): ?Department
    {
        if ($name !== '') {
            $dept = $this->em->getRepository(Department::class)->findOneBy(['name' => $name]);
            if ($dept) {
                return $dept;
            }
        }

        if ($code !== '') {
            return $this->em->getRepository(Department::class)->findOneBy(['code' => strtoupper($code)]);
        }

        return null;
    }

    private function resolveCity(string $name, ?Department $department): City
    {
        $city = $this->em->getRepository(City::class)->findOneBy([
            'name'       => $name,
            'department' => $department,
        ]);

        if (!$city instanceof City) {
            $city = (new City())->setName($name)->setDepartment($department);
            $this->em->persist($city);
        }

        return $city;
    }

    private function resolveAddress(
        string $placeId,
        string $formattedAddress,
        ?string $lat,
        ?string $lng,
        ?string $postalCode,
        City $city,
    ): Address {
        $address = $this->em->getRepository(Address::class)->findOneBy(['googlePlaceId' => $placeId]);

        if (!$address instanceof Address) {
            $address = (new Address())
                ->setGooglePlaceId($placeId);

            $this->em->persist($address);
        }

        $address
            ->setFormatted($formattedAddress)
            ->setLat($lat ?: null)
            ->setLng($lng ?: null)
            ->setPostalCode($postalCode ?? '')
            ->setCity($city);

        return $address;
    }
}
