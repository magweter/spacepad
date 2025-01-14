import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/login_controller.dart';
import 'package:pinput/pinput.dart';
import 'package:spacepad/theme.dart';

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
                          child: Text('Spacepad', style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w600,
                              height: 1.2
                          )),
                        ),

                        const SizedBox(height: 15),

                        SizedBox(
                          width: 350,
                          child: Text('Please enter the connect code in your account dashboard found at spacepad.magweter.com.', textAlign: TextAlign.center),
                        ),
                      ],
                    ),

                    const SizedBox(height: 60),

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

                    const SizedBox(height: 60),

                    SizedBox(
                      width: 400,
                      child: Obx(() => ElevatedButton(
                        onPressed: controller.submitActive ? controller.submit : null,
                        child: controller.loading.value ? const Spinner(size: 20) : Text('Connect to your account'),
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
