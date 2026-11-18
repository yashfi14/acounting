<?php

require_once __DIR__ . '/SmartLocalAIProvider.php';

/**
 * MockAIProvider - Backwards compatible wrapper around SmartLocalAIProvider
 */
class MockAIProvider extends SmartLocalAIProvider implements AIProviderInterface {
    // Inherits all intelligent intent recognition, RBAC, DB data fetching, and knowledge base from SmartLocalAIProvider
}
