import 'package:flutter/material.dart';

class QtyStepper extends StatelessWidget {
  const QtyStepper({
    super.key,
    required this.qty,
    required this.onChanged,
    this.min = 0.001,
    this.step = 1,
  });

  final double qty;
  final ValueChanged<double> onChanged;
  final double min;
  final double step;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _RoundBtn(
          icon: Icons.remove,
          onPressed: qty <= min ? null : () => onChanged((qty - step).clamp(min, 9999)),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          child: Text(
            qty == qty.roundToDouble() ? qty.toInt().toString() : qty.toStringAsFixed(2),
            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
          ),
        ),
        _RoundBtn(
          icon: Icons.add,
          onPressed: () => onChanged(qty + step),
        ),
      ],
    );
  }
}

class _RoundBtn extends StatelessWidget {
  const _RoundBtn({required this.icon, required this.onPressed});

  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onPressed,
        child: SizedBox(
          width: 36,
          height: 36,
          child: Icon(icon, size: 20),
        ),
      ),
    );
  }
}
