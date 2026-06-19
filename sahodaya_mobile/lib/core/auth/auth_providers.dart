import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../config/env.dart';
import '../api/api_client.dart';
import 'auth_repository.dart';
import 'auth_session.dart';
import 'secure_token_storage.dart';

final secureTokenStorageProvider = Provider<SecureTokenStorage>((ref) => SecureTokenStorage());

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final storage = ref.watch(secureTokenStorageProvider);
  final api = ApiClient(
    baseUrl: AppEnv.apiBaseUrl,
    tokenProvider: () => storage.readToken(),
  );
  return AuthRepository(api: api, storage: storage);
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureTokenStorageProvider);
  return ApiClient(
    baseUrl: AppEnv.apiBaseUrl,
    tokenProvider: () => storage.readToken(),
    onUnauthorized: () => ref.read(authControllerProvider.notifier).forceLogout(),
  );
});

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  const AuthState({
    required this.status,
    this.session,
    this.error,
  });

  final AuthStatus status;
  final AuthSession? session;
  final String? error;

  AuthState copyWith({
    AuthStatus? status,
    AuthSession? session,
    String? error,
  }) {
    return AuthState(
      status: status ?? this.status,
      session: session ?? this.session,
      error: error,
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repository) : super(const AuthState(status: AuthStatus.unknown));

  final AuthRepository _repository;

  Future<void> bootstrap() async {
    final session = await _repository.restoreSession();
    state = AuthState(
      status: session == null ? AuthStatus.unauthenticated : AuthStatus.authenticated,
      session: session,
    );
  }

  Future<void> login(String email, String password) async {
    state = state.copyWith(error: null);
    try {
      final session = await _repository.login(email: email, password: password);
      state = AuthState(status: AuthStatus.authenticated, session: session);
    } catch (error) {
      state = AuthState(
        status: AuthStatus.unauthenticated,
        error: error.toString(),
      );
      rethrow;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  Future<void> forceLogout() async {
    await _repository.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authControllerProvider = StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(ref.watch(authRepositoryProvider));
});
