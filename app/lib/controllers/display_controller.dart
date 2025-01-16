import 'package:get/get.dart';
import 'package:spacepad/models/display_model.dart';
import 'package:spacepad/components/toast.dart';
import 'package:spacepad/services/device_service.dart';
import 'package:spacepad/services/display_service.dart';
import 'package:spacepad/services/auth_service.dart';

class DisplayController extends GetxController {
  final RxBool loading = RxBool(false);
  final RxList<DisplayModel> displays = RxList();
  final Rx<DisplayModel?> selectedDisplay = Rx(null);

  @override
  void onInit() {
    super.onInit();

    getDisplays();
  }

  void onSelect(val) {
    selectedDisplay.value = val;
  }

  bool get submitActive {
    return selectedDisplay.value != null;
  }

  Future<void> getDisplays() async {
    if (loading.value) return;

    loading.value = true;

    try {
      displays.value = await DisplayService.instance.getDisplays();
    } catch (e) {
      Toast.showError('Could not load displays');
    }

    loading.value = false;
  }

  Future<void> submit() async {
    if (loading.value) return;

    loading.value = true;

    try {
      await DeviceService.instance.changeDisplay(selectedDisplay.value!.id);

      await AuthService.instance.verify();
    } catch (e) {
      Toast.showError('An unexpected error arisen. Please check if you have an internet connection');
    }

    loading.value = false;
  }
}