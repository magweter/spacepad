import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/controllers/led_test_controller.dart';
import 'package:spacepad/theme.dart';
import 'package:tailwind_components/tailwind_components.dart';

/// Test page for the LED strips on Philips displays (SICP). Only reachable in a LED test build.
class LedTestPage extends StatelessWidget {
  const LedTestPage({super.key});

  @override
  Widget build(BuildContext context) {
    LedTestController controller = Get.put(LedTestController());

    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  _buildBackButton(),
                  const SizedBox(width: 16),
                  Text('led_test'.tr, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600, height: 1.2)),
                ],
              ),
              const SizedBox(height: 20),
              LayoutBuilder(builder: (context, constraints) {
                final left = [
                  _buildConnection(controller),
                  const SizedBox(height: 16),
                  _buildColors(controller),
                  const SizedBox(height: 16),
                  _buildLastResult(controller),
                ];
                final right = [
                  _buildStatus(controller),
                  const SizedBox(height: 16),
                  _buildDevice(controller),
                  const SizedBox(height: 16),
                  _buildLog(controller),
                ];

                if (constraints.maxWidth < 800) {
                  return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [...left, const SizedBox(height: 16), ...right]);
                }

                return Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: left)),
                    const SizedBox(width: 16),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: right)),
                  ],
                );
              }),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBackButton() {
    return Container(
      decoration: BoxDecoration(
        color: TWColors.gray_500.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: TWColors.gray_500.withValues(alpha: 0.3)),
      ),
      child: TextButton(
        onPressed: () => Get.back(),
        child: Text(
          'led_back'.tr,
          style: const TextStyle(color: TWColors.gray_700, fontSize: 14, fontWeight: FontWeight.w500),
        ),
      ),
    );
  }

  Widget _panel(String title, List<Widget> children, {Widget? trailing}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: TWColors.gray_200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600))),
              if (trailing != null) trailing,
            ],
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _buildConnection(LedTestController controller) {
    return _panel(
      'led_connection'.tr,
      [
        Row(
          children: [
            Expanded(flex: 3, child: _field('led_address'.tr, controller.host, TextInputType.url)),
            const SizedBox(width: 10),
            Expanded(flex: 1, child: _field('led_port'.tr, controller.port, TextInputType.number, digitsOnly: true)),
            const SizedBox(width: 10),
            Expanded(flex: 1, child: _field('led_monitor_id'.tr, controller.monitorId, TextInputType.number, digitsOnly: true)),
          ],
        ),
        const SizedBox(height: 12),
        Obx(() => ElevatedButton(
          onPressed: controller.busy.value ? null : controller.testConnection,
          child: controller.connection.value == LedConnection.checking ? const Spinner(size: 20) : Text('led_retest'.tr),
        )),
      ],
      trailing: Obx(() => _buildConnectionBadge(controller.connection.value)),
    );
  }

  Widget _buildConnectionBadge(LedConnection state) {
    final (label, color) = switch (state) {
      LedConnection.notTested => ('led_not_tested'.tr, TWColors.gray_500),
      LedConnection.checking => ('led_checking'.tr, TWColors.gray_500),
      LedConnection.connected => ('led_connected'.tr, TWColors.green_600),
      LedConnection.unreachable => ('led_unreachable'.tr, TWColors.red_600),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: color.withValues(alpha: 0.3)),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.w600)),
    );
  }

  Widget _field(String label, TextEditingController textController, TextInputType keyboard, {bool digitsOnly = false}) {
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(10),
      borderSide: const BorderSide(color: AppTheme.oxford),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
        const SizedBox(height: 6),
        TextField(
          controller: textController,
          keyboardType: keyboard,
          inputFormatters: digitsOnly ? [FilteringTextInputFormatter.digitsOnly] : null,
          style: const TextStyle(fontSize: 14),
          decoration: InputDecoration(
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
            border: border,
            enabledBorder: border,
            focusedBorder: border,
          ),
        ),
      ],
    );
  }

  Widget _buildColors(LedTestController controller) {
    return _panel('led_colour'.tr, [
      Obx(() => Row(
        children: [
          for (final color in LedColor.all) ...[
            if (color != LedColor.all.first) const SizedBox(width: 10),
            Expanded(child: _buildColorButton(color, controller.busy.value ? null : () => controller.setColor(color))),
          ],
        ],
      )),
    ]);
  }

  Widget _buildColorButton(LedColor color, VoidCallback? onPressed) {
    return Opacity(
      opacity: onPressed != null ? 1.0 : 0.5,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            height: 88,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppTheme.oxford),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    color: color.swatch,
                    shape: BoxShape.circle,
                    border: Border.all(color: TWColors.gray_300),
                  ),
                ),
                const SizedBox(height: 8),
                Text(color.label.tr, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildLastResult(LedTestController controller) {
    return Obx(() {
      final entry = controller.lastResult.value;
      if (entry == null) return const SizedBox.shrink();

      final color = entry.ok ? TWColors.green_600 : TWColors.red_600;
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withValues(alpha: 0.3)),
        ),
        child: Text(
          '${'led_last_result'.tr}: ${entry.action}. ${entry.result}',
          style: TextStyle(color: color, fontSize: 14, fontWeight: FontWeight.w600),
        ),
      );
    });
  }

  Widget _buildStatus(LedTestController controller) {
    return _panel('led_read_status'.tr, [
      Obx(() {
        final state = controller.reportedState.value;
        final error = controller.reportedError.value;
        if (state == null && error.isEmpty) return const SizedBox.shrink();

        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(
            children: [
              if (state != null) ...[
                Container(
                  width: 20,
                  height: 20,
                  decoration: BoxDecoration(
                    color: state.on ? Color.fromARGB(255, state.r, state.g, state.b) : Colors.black,
                    shape: BoxShape.circle,
                    border: Border.all(color: TWColors.gray_300),
                  ),
                ),
                const SizedBox(width: 10),
              ],
              Expanded(
                child: Text(
                  state == null ? error : (state.on ? 'led_status_on'.trParams({'color': state.hex}) : 'led_status_off'.tr),
                  style: TextStyle(fontSize: 14, color: state == null ? TWColors.red_600 : null),
                ),
              ),
            ],
          ),
        );
      }),
      Obx(() => ElevatedButton(
        onPressed: controller.busy.value ? null : controller.readStatus,
        child: Text('led_read_status'.tr),
      )),
    ]);
  }

  Widget _buildDevice(LedTestController controller) {
    return _panel('led_device'.tr, [
      Obx(() => Text(
        '${'led_model'.tr}: ${controller.device.value}\n'
        '${'led_android'.tr}: ${controller.androidVersion.value}\n'
        '${'led_app_version'.tr}: ${controller.appVersion}',
        style: const TextStyle(fontSize: 14, height: 1.5),
      )),
    ]);
  }

  Widget _buildLog(LedTestController controller) {
    return _panel('led_log'.tr, [
      Obx(() {
        if (controller.log.isEmpty) {
          return Text('led_log_empty'.tr, style: const TextStyle(fontSize: 14, color: TWColors.gray_500));
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final entry in controller.log)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${entry.timeLabel}  ${entry.action}: ${entry.result}',
                      style: TextStyle(fontSize: 13, color: entry.ok ? TWColors.gray_900 : TWColors.red_600),
                    ),
                    if (entry.tx.isNotEmpty)
                      Text(
                        'TX ${entry.tx}   RX ${entry.rx.isEmpty ? '-' : entry.rx}',
                        style: const TextStyle(fontSize: 11, fontFamily: 'monospace', color: TWColors.gray_500),
                      ),
                  ],
                ),
              ),
          ],
        );
      }),
    ]);
  }
}
