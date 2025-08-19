import '../api/tts_api_service.dart';

/// Abstract purchase flow interface so UI can trigger acquisitions for locked products.
abstract class PurchaseHandler {
  Future<bool> purchaseProduct(TtsProductDto product);
}

/// Mock implementation example; integrate real PayPal/Stripe or in-app purchase.
class MockPurchaseHandler implements PurchaseHandler {
  @override
  Future<bool> purchaseProduct(TtsProductDto product) async {
    await Future.delayed(const Duration(milliseconds: 600));
    return true; // pretend success
  }
}
