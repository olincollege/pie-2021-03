---
layout: page
title: Systems
permalink: /systems/
---

## Mechanical
The mechanical design was broken into two parts for the detachable and extendable blade.

#### Detachable

The detachable lightsaber has 2 main components: the hilt which holds the electronics and the blade which houses the LED strip.

<p align="center">
  <img src="../Photos!/" alt="Completed Detachable blade">
</p>

The blade is made of a piece of diffused polycarbonate tubing with two endcaps holding a strip of LEDs in the center. The diffusion was done by rubbing the polycarbonate tubing with an emery cloth and placing 0.002 inch thick polycarbonate film on the inside of the tube. The upper endcap covers the top of the blade and is connected to the top of the LED strip, so the LEDs hang through the blade tubing. The lower endcap, in black, also holds a connector that interfaces with a matching connector on the hilt. Together, the lower endcap and matching connector act as the deaching mechanism to remove the blade from the hilt, allowing the LED strip to be connected to the hilt electronics when the blade is attached.

**CAD OF THE FINAL DETACHING MECHANISM**

<p align="center">
  <img src="../Photos!/sprint3detachablev7.jpg" alt="Detaching Mechanism">
</p>
 
The hilt is made of several cylinders which thread together and each hold various electrical components such as the battery, speaker, and control board. The components are secured with hidden supports inside the hilt pieces. The speaker is housed in the very bottom of the hilt, and the battery is hosed in the component above it. This allows for easy access to the Lipo battery for recharging. Then, the buttons and switch **? - not that important** are housed in the third component, and the control board is housed in the top component. The control board is revealed here in our version of kyber crystal reveal that some lightsabers have. This top component is then connected to the hilt connector of the detaching mechanism.

 <p align="center">
  <img src="../Photos!/FullCAD2.PNG" alt="Hilt Cylinders CAD Model">
</p>

**PICTURE OF FINAL HILT PRINT? IF NOT USE VERSION 1**

#### Extendable
Extendable/Retractable Blade Mechanism:

3 primary components: 
LED Strip spool connected to electronics 

Tape measure spool connected to motor friction wheel

Motor friction wheel driving tape measure spool

Brief Overview:
The ends of the LED strip and the tape measure are connected and secured together via hot glue. There are 3 primary electronic switches: 1 moves the tape measure blade forward, 1 moves the tape measure blade backwards and 1 turns on/off the LED strip and sound effects. By pressing both the blade forward and blade on buttons, the lightsaber blade is extended and activated. By pressing both the blade backward and blade off buttons, the blade is retracted and turned off. 

The motor friction wheel drives the tape measure blade forward or backward by applying a torque to the outside of the coiled tape measure blade.

Both LED strip spool and tape measure blade spool installed with clock springs that provide a constant counterclockwise torque acting to pull the LED strip and tape measure blade back into the hilt. Consequently, these clock springs provide tension that keeps the blade straight.

Blade angle tensioner (metal rod that can rotate with nominal friction) present in the blade opening presses the LED strip flat against the tape measure blade, ensures that the force applied by the LED strip to the end of tape measure blade (at the connection) is  as close to parallel to the tape measure blade as possible. This force needs to be parallel, or else the tension from the LED strip will pull the tape measure blade upwards and crumple the tape measure blade.

3 primary components:The detachable lightsaber has 2 main components, the hilt and the blade.

  - LED Strip spool connected to the electronics
  - Tape measure spool connected to motor friction wheel
  - Motor friction wheel driving tape measure spool

Brief Overview:
The ends of the LED strip and the tape measure are connected and secured together via hot glue. There are 3 primary electronic switches: 1 moves the tape measure blade forward, 1 moves the tape measure blade backwards and 1 turns on/off the LED strip and sound effects. By pressing both the blade forward and blade on buttons, the lightsaber blade is extended and activated. By pressing both the blade backward and blade off buttons, the blade is retracted and turned off.

The motor friction wheel drives the tape measure blade forward or backward by applying a torque to the outside of the coiled tape measure blade.

Both LED strip spool and tape measure blade spool installed with clock springs that provide a constant counterclockwise torque acting to pull the LED strip and tape measure blade back into the hilt. Consequently, these clock springs provide tension that keeps the blade straight.

Blade angle tensioner (metal rod that can rotate with nominal friction) present in the blade opening presses the LED strip flat against the tape measure blade, ensures that the force applied by the LED strip to the end of tape measure blade (at the connection) is  as close to parallel to the tape measure blade as possible. This force needs to be parallel, or else the tension from the LED strip will pull the tape measure blade upwards and crumple the tape measure blade.

## Electrical
We developed a system that allowed the user to change the color of the lightsaber and made the lightsaber react to movement. We needed the system to be compact such that it could be contained in the hilt of the lightsaber, which needs to fit comfortably in someone's hand.

We worked with a Feather M4 which runs CircuitPython natively on the board, allowing us to use the UF2 bootloader to update the code on the board. Then, we added the FeatherWing board on top of the Feather M4. We choose this board as it came with a NeoPixel port for LEDs, a triple-axis accelerometer, and a Class D audio amplifier, key requirements for a lightsaber.

For the first iteration, we wanted to create a system that would meet the MVP electrical requirements: turn a strip of LEDs on and off while producing sounds based on button input. We achieved this by connecting a 4 Ohm, 3 Watt speaker to the audio connector on the FeatherWing, connecting the 0.5 meter NeoPixel LEDs to the JST port on the FeatherWing, and connecting a push button to the switch and ground pins. Additionally, we connected a 3.7 volt Lipo battery to the Feather M4's Lipoly JST jack to power the boards. This set up is shown in the circuit diagram below.

<p align="center">
  <img src="../Photos!/sprint1electrical.jpg" alt="Sprint 1 Circuit Diagram">
</p>

**WHAT ARE THE R,G,B DIODES FOR?**

We found that this system did meet the MVP electrical requirements, so for our second and final iteration, we added a button which would allow us to switch between lightsaber blade colors. This set up is shown in the circuit diagram below.

**IS THE SWITCH BEING USED? IF SO WHAT FOR?**

<p align="center">
  <img src="../Photos!/sprint2electrical.png" alt="Sprint 2 Circuit Diagram">
</p>

Unfortunately, since we chose to create two different lightsabers, we could not spare more money for the electrical system at this time. However, in future iterations, we would like to add sensors in the blade of the lightsaber. Specifically, we would add one in the tip of the blade to allow us to create blade drag effect as seen in [The Force Awakens](https://youtu.be/FJTz-ahXyyI?t=247) and a material cutting effect as seen in [The Phantom Menace](https://youtu.be/K48M2S7bkSA?t=1). However, sensors along the blade would allow us flash the blade white in the location it was hit instead of flashing the whole blade white.

## Software

Future iterations add kylo ren unstable blade effects - vary intensity of red leds? add some orange in? could follow a pattern 