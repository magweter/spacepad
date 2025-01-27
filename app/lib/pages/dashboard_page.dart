import 'package:flutter/material.dart';
import 'package:spacepad/components/event_line.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/dashboard_controller.dart';
import 'package:spacepad/models/event_model.dart';
import 'package:get/get.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key});

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
            color: controller.isTransitioning ?
              TWColors.amber_500 :
              (controller.isReserved ? TWColors.rose_600 : TWColors.green_600),
            padding: const EdgeInsets.all(16),
            child: Container(
                height: double.infinity,
                width: double.infinity,
                decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(10),
                    color: Colors.black
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(40, 30, 40, 30),
                  child: Stack(
                    children: [

                      Align(
                        alignment: Alignment.topLeft,
                        child: Text(controller.time.value, style: TextStyle(color: TWColors.gray_300, fontSize: 28, fontWeight: FontWeight.w900))
                      ),
                      Align(
                        alignment: Alignment.topRight,
                        child: Text(controller.roomName, style: TextStyle(color: TWColors.gray_300, fontSize: 28, fontWeight: FontWeight.w700))
                      ),

                      SpaceCol(
                        spaceBetween: 40,
                        mainAxisSize: MainAxisSize.max,
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [

                          SpaceCol(
                            spaceBetween: controller.meetingInfo != null ? 10 : 0,
                            children: [
                              Text(controller.title, style: TextStyle(color: Colors.white, fontSize: 60, fontWeight: FontWeight.w900)),
                              SpaceRow(
                                spaceBetween: 20,
                                children: [
                                  if (controller.meetingInfo != null) Container(
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(10),
                                      color: TWColors.gray_600.withValues(alpha: 0.3),
                                    ),
                                    child: Padding(
                                      padding: const EdgeInsets.fromLTRB(20,10,20,10),
                                      child: Text(controller.meetingInfo!, style: TextStyle(color: TWColors.white, fontSize: 36, fontWeight: FontWeight.w400)),
                                    ),
                                  ),
                                  Text(controller.subtitle, style: TextStyle(color: TWColors.gray_300, fontSize: 40, fontWeight: FontWeight.w400)),
                                ]
                              ),
                              SizedBox(height: 20)
                            ],
                          ),

                        ],
                      ),

                      if (controller.upcomingEvents.isNotEmpty) Align(
                          alignment: Alignment.bottomLeft,
                          child: Container(
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(10),
                              color: TWColors.gray_600.withValues(alpha: 0.3),
                            ),
                            child: Padding(
                              padding: const EdgeInsets.all(20),
                              child: SpaceCol(
                                spaceBetween: 15,
                                children: [
                                  for (EventModel event in controller.upcomingEvents.take(1)) EventLine(event: event),
                                ],
                              ),
                            ),
                          ),
                      ),

                    ],
                  )
                ),
              )
          ),
      ),
    );
  }
}