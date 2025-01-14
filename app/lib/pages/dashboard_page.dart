import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:spacepad/components/event_card.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key});

  String formatDateTime(DateTime date) {
    return DateFormat('HH:mm').format(date);
  }

  @override
  Widget build(BuildContext context) {
    DashboardController controller = Get.put(DashboardController());

    return Scaffold(
      backgroundColor: AppTheme.black,
      body: Obx(() => controller.loading.value ?
          Center(
            child: Spinner(size: 40, thickness: 4, color: AppTheme.platinum),
          ) :
          Container(
            height: double.infinity,
            width: double.infinity,
            color: controller.currentlyInMeeting ? TWColors.red_600 : TWColors.green_600,
            padding: const EdgeInsets.all(20),
            child: SafeArea(
              top: false,
              child: Container(
                height: double.infinity,
                width: double.infinity,
                decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(10),
                    color: AppTheme.black
                ),
                child: Padding(
                  padding: const EdgeInsets.all(50),
                  child: Row(
                    children: [
                      Expanded(
                        flex: 3,
                        child: SpaceCol(
                          spaceBetween: 12,
                          mainAxisSize: MainAxisSize.max,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(controller.currentMeeting?.summary ?? 'Beschikbaar', style: TextStyle(color: controller.currentlyInMeeting ? TWColors.red_600 : TWColors.green_600, fontSize: 44, fontWeight: FontWeight.bold)),

                            if (controller.currentlyInMeeting) Text('${formatDateTime(controller.currentMeeting!.start)} tot ${formatDateTime(controller.currentMeeting!.end)}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 24,
                                fontWeight: FontWeight.w400,
                              ),
                            ),
                          ],
                        ),
                      ),

                      Expanded(
                        flex: 2,
                        child: SpaceCol(
                          spaceBetween: 20,
                          mainAxisSize: MainAxisSize.max,
                          children: [
                            Text('Komende meetings',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 32,
                                fontWeight: FontWeight.bold,
                              ),
                            ),

                            Expanded(
                              child: SingleChildScrollView(
                                child: SpaceCol(
                                  spaceBetween: 15,
                                  children: [
                                    if (controller.getNextEvents().isEmpty) const Text('Geen meetings meer vandaag...',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 16,
                                      ),
                                    ),

                                    for (EventModel event in controller.getNextEvents()) EventCard(event: event),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            )
          ),
      ),
    );
  }
}