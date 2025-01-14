class DisplayModel {
  String id;
  String name;

  DisplayModel({
    required this.id,
    required this.name,
  });

  factory DisplayModel.fromJson(Map data) {
    return DisplayModel(
      id: data['id'],
      name: data['name'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
    };
  }
}