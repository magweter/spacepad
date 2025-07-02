class UserModel {
  String id;
  String name;
  String email;
  bool hasPro;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.hasPro = false,
  });

  factory UserModel.fromJson(Map data) {
   return UserModel(
      id: data['id'],
      name: data['name'],
      email: data['email'],
      hasPro: data['hasPro'] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'hasPro': hasPro,
    };
  }

  bool get isPro => hasPro;
}