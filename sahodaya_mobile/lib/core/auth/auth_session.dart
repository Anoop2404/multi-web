class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.tenantId,
    this.tenantName,
    this.logoUrl,
    this.emailVerified = true,
  });

  final int id;
  final String name;
  final String email;
  final String role;
  final String tenantId;
  final String? tenantName;
  final String? logoUrl;
  final bool emailVerified;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? json;
    return AuthUser(
      id: (user['id'] as num).toInt(),
      name: user['name'] as String? ?? '',
      email: user['email'] as String? ?? '',
      role: (json['role'] ?? user['role']) as String? ?? '',
      tenantId: (json['tenant_id'] ?? user['tenant_id']) as String? ?? '',
      tenantName: (json['tenant_name'] ?? user['tenant_name']) as String?,
      logoUrl: (json['logo_url'] ?? user['logo_url']) as String?,
      emailVerified: user['email_verified'] as bool? ?? true,
    );
  }
}

class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final AuthUser user;

  bool get isSchoolAdmin => user.role == 'school_admin';
  bool get isSahodayaAdmin => user.role == 'sahodaya_admin';
}
