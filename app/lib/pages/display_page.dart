import 'package:dropdown_button2/dropdown_button2.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/display_controller.dart';
import 'package:spacepad/models/display_model.dart';

class DisplayPage extends StatelessWidget {
  const DisplayPage({super.key});

  @override
  Widget build(BuildContext context) {
    DisplayController controller = Get.put(DisplayController());

    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: SingleChildScrollView(
        child: SafeArea(
          child: Container(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 60),
              alignment: Alignment.center,
              height: MediaQuery.sizeOf(context).height,
              child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: Text('choose_display'.tr, style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w600,
                              height: 1.2
                          )),
                        ),

                        const SizedBox(height: 15),

                        SizedBox(
                          width: 350,
                          child: Text('choose_room_display'.tr, textAlign: TextAlign.center),
                        ),
                      ],
                    ),

                    const SizedBox(height: 60),

                    SizedBox(
                      width: 400,
                      child: Obx(() =>
                        DropdownButtonFormField2<DisplayModel>(
                          isExpanded: true,
                          decoration: InputDecoration(
                            // Add Horizontal padding using menuItemStyleData.padding so it matches
                            // the menu padding when button's width is not specified.
                            contentPadding: const EdgeInsets.symmetric(vertical: 16),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                            // Add more decoration..
                          ),
                          hint: Text(
                            'select_display'.tr,
                            style: TextStyle(fontSize: 14),
                          ),
                          items: controller.displays
                              .map((item) => DropdownMenuItem<DisplayModel>(
                                  value: item,
                                  child: Text(
                                    item.name,
                                    style: const TextStyle(
                                      fontSize: 14,
                                    ),
                                  ),
                                ))
                              .toList(),
                          validator: (value) {
                            if (value == null) {
                              return 'please_select_display'.tr;
                            }
                            return null;
                          },
                          onChanged: (value) {
                            controller.onSelect(value);
                          },
                          buttonStyleData: const ButtonStyleData(
                            padding: EdgeInsets.only(right: 8),
                          ),
                          iconStyleData: const IconStyleData(
                            icon: Icon(
                              Icons.arrow_drop_down,
                              color: Colors.black45,
                            ),
                            iconSize: 24,
                          ),
                          dropdownStyleData: DropdownStyleData(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          menuItemStyleData: const MenuItemStyleData(
                            padding: EdgeInsets.symmetric(horizontal: 16),
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 60),

                    SizedBox(
                      width: 400,
                      child: Obx(() => ElevatedButton(
                        onPressed: controller.submitActive ? controller.submit : null,
                        child: controller.loading.value ? const Spinner(size: 20) : Text('continue'.tr),
                      )),
                    ),
                  ]
              ),

          ),
        )
      ),
    );
  }
}
