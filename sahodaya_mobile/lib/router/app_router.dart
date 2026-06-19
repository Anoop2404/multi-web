import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/auth/auth_providers.dart';
import '../features/auth/login_screen.dart';
import '../features/auth/portal_screen.dart';
import '../features/auth/school_register_screen.dart';
import '../features/auth/splash_screen.dart';
import '../features/sahodaya/sahodaya_shell.dart';
import '../features/school/school_shell.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authControllerProvider);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: _RouterRefresh(ref),
    redirect: (context, state) {
      final status = authState.status;
      final location = state.matchedLocation;
      final onPortal = location == '/portal';
      final onLogin = location == '/login';
      final onRegister = location == '/register';
      final splashing = location == '/splash';

      if (status == AuthStatus.unknown) {
        return splashing ? null : '/splash';
      }

      if (status == AuthStatus.unauthenticated) {
        return onPortal || onLogin || onRegister ? null : '/portal';
      }

      final session = authState.session;
      if (session == null) {
        return '/portal';
      }

      if (onLogin || splashing || onPortal || onRegister) {
        return session.isSahodayaAdmin ? '/sahodaya' : '/school';
      }

      if (session.isSchoolAdmin && location.startsWith('/sahodaya')) {
        return '/school';
      }
      if (session.isSahodayaAdmin && location.startsWith('/school')) {
        return '/sahodaya';
      }

      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/portal', builder: (_, __) => const PortalScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, __) => const SchoolRegisterScreen()),
      GoRoute(
        path: '/school',
        builder: (_, __) => const SchoolShell(),
      ),
      GoRoute(
        path: '/sahodaya',
        builder: (_, __) => const SahodayaShell(),
      ),
    ],
  );
});

class _RouterRefresh extends ChangeNotifier {
  _RouterRefresh(this.ref) {
    ref.listen<AuthState>(authControllerProvider, (_, __) => notifyListeners());
  }

  final Ref ref;
}
