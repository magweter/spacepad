import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/login_controller.dart';
import 'package:pinput/pinput.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class LoginPage extends StatelessWidget {
  const LoginPage({super.key});

  @override
  Widget build(BuildContext context) {
    LoginController controller = Get.put(LoginController());

    final defaultPinTheme = PinTheme(
      width: 60,
      height: 60,
      textStyle: const TextStyle(
        fontSize: 22,
        color: Color.fromRGBO(30, 60, 87, 1),
      ),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppTheme.oxford),
      ),
    );

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
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.asset('assets/logo.png', width: 80),
                        ),

                        const SizedBox(height: 40),

                        Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: Text('introduction_title'.tr, style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w600,
                              height: 1.2
                          )),
                        ),

                        const SizedBox(height: 10),

                        SizedBox(
                          width: 350,
                          child: Text('introduction_text'.tr, textAlign: TextAlign.center),
                        ),
                      ],
                    ),

                    const SizedBox(height: 30),

                    // Self-hosting button group
                    SizedBox(
                      width: 400,
                      child: Obx(() => Container(
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: AppTheme.oxford),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: InkWell(
                                onTap: () => controller.toggleSelfHosted(false),
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: !controller.isSelfHosted.value ? AppTheme.oxford : Colors.white,
                                    borderRadius: const BorderRadius.only(
                                      topLeft: Radius.circular(9),
                                      bottomLeft: Radius.circular(9),
                                    ),
                                  ),
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  child: Center(
                                    child: Text(
                                      'cloud_hosted'.tr,
                                      style: TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w500,
                                        color: !controller.isSelfHosted.value ? Colors.white : AppTheme.oxford,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            Container(
                              width: 1,
                              color: AppTheme.oxford,
                            ),
                            Expanded(
                              child: InkWell(
                                onTap: () => controller.toggleSelfHosted(true),
                                child: Container(
                                  decoration: BoxDecoration(
                                    color: controller.isSelfHosted.value ? AppTheme.oxford : Colors.white,
                                    borderRadius: const BorderRadius.only(
                                      topRight: Radius.circular(9),
                                      bottomRight: Radius.circular(9),
                                    ),
                                  ),
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  child: Center(
                                    child: Text(
                                      'self_hosted'.tr,
                                      style: TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w500,
                                        color: controller.isSelfHosted.value ? Colors.white : AppTheme.oxford,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      )),
                    ),

                    const SizedBox(height: 15),

                    // Self-hosted URL input
                    Obx(() => controller.isSelfHosted.value
                        ? SizedBox(
                            width: 400,
                            child: TextField(
                              decoration: InputDecoration(
                                labelText: 'self_hosted_url'.tr,
                                hintText: 'url_hint'.tr,
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(10),
                                  borderSide: const BorderSide(color: AppTheme.oxford),
                                ),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(10),
                                  borderSide: const BorderSide(color: AppTheme.oxford),
                                ),
                                focusedBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(10),
                                  borderSide: const BorderSide(color: AppTheme.oxford),
                                ),
                              ),
                              onChanged: controller.urlChanged,
                            ),
                          )
                        : const SizedBox.shrink()),

                    const SizedBox(height: 20),

                    SizedBox(
                      width: 400,
                      child: Text('enter_connect_code'.tr, textAlign: TextAlign.start),
                    ),

                    const SizedBox(height: 15),

                    SizedBox(
                      width: 400,
                      child: Directionality(
                        textDirection: TextDirection.ltr,
                        child: Pinput(
                          length: 6,
                          onChanged: controller.codeChanged,
                          defaultPinTheme: defaultPinTheme,
                          mainAxisAlignment: MainAxisAlignment.center,
                          separatorBuilder: (index) => const SizedBox(width: 8),
                          hapticFeedbackType: HapticFeedbackType.lightImpact,
                        ),
                      ),
                    ),

                    const SizedBox(height: 30),

                    SizedBox(
                      width: 400,
                      child: Obx(() => ElevatedButton(
                        onPressed: controller.submitActive.value ? controller.submit : null,
                        child: controller.loading.value ? const Spinner(size: 20) : Text('connect_to_account'.tr),
                      )),
                    ),

                    const SizedBox(height: 40),

                    // Connect code explanation
                    SizedBox(
                      width: 350,
                      child: Text(
                        'connect_code_explanation'.tr,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.grey,
                        ),
                      ),
                    ),
                  ]
              ),
          ),
        )
      ),
    );
  }
}
