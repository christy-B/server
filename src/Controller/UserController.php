<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserController extends AbstractController
{
    //recuperer tous les utilisateurs
    #[Route('api/users', name: 'get_user', methods: ['GET'])]
    public function getUsers(EntityManagerInterface $manager, SerializerInterface $serializer): Response
    {
        // Récupérer tous les utilisateurs depuis la base de données
        $users = $manager->getRepository(User::class)->findAll();

        // Sérialiser les utilisateurs en JSON
        $jsonUsers = $serializer->serialize($users, 'json');

        // Retourner la réponse JSON
        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    //creer un utilisateurs
    #[Route('api/users', name: 'create_user', methods: ['POST'])]
    public function createUser(EntityManagerInterface $manager, Request $request, ValidatorInterface $validator, SerializerInterface $serializer): Response
    {
        // Désérialiser le JSON en un objet User
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        // Vérifier si l'email existe déjà
        $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        if ($existingUser) {
            return new JsonResponse(['message' => 'Email existe déjà.'], Response::HTTP_CONFLICT);
        }

        // Définir la date de création
        $user->setCreatedAt(new \DateTimeImmutable());

        // Valider les données
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['message' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Sauvegarde en base
        $manager->persist($user);
        $manager->flush();

        // Retourner une réponse de succès
        return new JsonResponse(['message' => 'Utilisateur créé avec succès.'], Response::HTTP_CREATED);
    }


    //modifier un utilisateur
    #[Route('api/users/{id}', name: 'update_user', methods: ['PUT'])]
    public function updateUser(int $id, EntityManagerInterface $manager, Request $request, ValidatorInterface $validator, SerializerInterface $serializer): Response
    {
        // Récupérer l'utilisateur existant
        $user = $manager->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['message' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Récupérer les données envoyées
        $requestData = json_decode($request->getContent(), true);
        if (!$requestData) {
            return new JsonResponse(
                ['message' => 'Format JSON invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Vérifier si l'email a été modifié
        if (isset($requestData['email']) && $requestData['email'] !== $user->getEmail()) {
            $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => $requestData['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return new JsonResponse(['message' => 'Cet email est déjà utilisé par un autre utilisateur.'], Response::HTTP_CONFLICT);
            }
        }

        try {
            // Désérialisation et mise à jour de l'utilisateur
            $serializer->deserialize($request->getContent(), User::class, 'json', ['object_to_populate' => $user]);
        } catch (\Exception $e) {
            return new JsonResponse(
                ['message' => 'Erreur lors du traitement des données. Vérifiez le format JSON.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validation des nouvelles données
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }
        // Mettre à jour en base de données
        $manager->flush();

        // Retourner une réponse de succès
        return new JsonResponse(['message' => 'Utilisateur mis à jour avec succès.'], Response::HTTP_OK);
    }

    //supprimer un utilisateur
    #[Route('/api/users/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(int $id, EntityManagerInterface $manager): JsonResponse
    {
        // Récupérer l'utilisateur par son ID
        $user = $manager->getRepository(User::class)->find($id);

        // Vérifier si l'utilisateur existe
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // Supprimer l'utilisateur
        $manager->remove($user);
        $manager->flush();

        // Retourner une réponse de succès
        return new JsonResponse(['message' => 'Utilisateur supprimé avec succès.'], Response::HTTP_OK);
    }
}
